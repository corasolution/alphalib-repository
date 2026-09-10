<?php

namespace Alpha\Journal\Http\Controllers\Admin;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\JournalSetting;
use Alpha\Journal\Models\Scopes\JournalScope;
use Alpha\Journal\Models\Section;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Creating and administering journals — a site-level action, so it's gated on SitePolicy::manage
 * (is_site_admin) rather than JournalPolicy.
 *
 * Deliberately NOT gated via Gate::authorize('create', Journal::class): JournalPolicy::before()
 * types its third parameter ?Journal, and Laravel passes the class-string for model-less checks,
 * which TypeErrors. SitePolicy::manage takes no model argument and sidesteps that entirely.
 */
class JournalController extends Controller
{
    /** A journal with no sections can be submitted to, but never gets a structured review form. */
    private const DEFAULT_SECTION = 'Articles';

    public function index(): Response
    {
        Gate::authorize('manage', Site::class);

        $site = Site::firstOrFail();
        $locale = $site->primary_locale;

        // Disabled journals are listed here on purpose — this screen is the only way back to a
        // journal that's been taken offline (the dashboard filters them out).
        $journals = Journal::orderBy('path')->get()->map(fn ($journal) => [
            'id' => $journal->id,
            'path' => $journal->path,
            'title' => $journal->title->get($locale, 'en') ?? $journal->path,
            'isEnabled' => $journal->is_enabled,
            'submissionCount' => Submission::withoutGlobalScope(JournalScope::class)
                ->where('journal_id', $journal->id)
                ->count(),
            'publishedCount' => Submission::withoutGlobalScope(JournalScope::class)
                ->where('journal_id', $journal->id)
                ->where('stage', SubmissionStage::Published->value)
                ->count(),
        ]);

        return Inertia::render('admin/journals/index', ['journals' => $journals]);
    }

    public function create(): Response
    {
        Gate::authorize('manage', Site::class);

        return Inertia::render('admin/journals/create', [
            'managers' => $this->assignableUsers(),
            'locales' => Site::firstOrFail()->installed_locales ?? ['en'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $site = Site::firstOrFail();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9-]*$/', 'unique:journals,path'],
            'primary_locale' => ['required', 'string', 'max:10'],
            'manager_id' => ['required', 'integer', 'exists:users,id'],
        ], [
            'path.regex' => 'The path may only contain lowercase letters, numbers and hyphens, and must start with a letter or number.',
        ]);

        // All-or-nothing: a journal without a manager is administrable by nobody but a site
        // admin, which is the exact failure this screen exists to prevent.
        $journal = DB::transaction(function () use ($data, $site) {
            $journal = Journal::create([
                'site_id' => $site->id,
                'path' => $data['path'],
                'title' => [$data['primary_locale'] => $data['title']],
                'primary_locale' => $data['primary_locale'],
                'installed_locales' => [$data['primary_locale']],
                'is_enabled' => true,
            ]);

            $journal->grantRole(User::findOrFail($data['manager_id']), JournalRole::JournalManager);

            Section::create([
                'journal_id' => $journal->id,
                'title' => [$data['primary_locale'] => self::DEFAULT_SECTION],
                'sort_order' => 0,
            ]);

            return $journal;
        });

        return redirect()
            ->route('admin.journals.index')
            ->with('success', "Journal \"{$data['title']}\" created.");
    }

    public function edit(Journal $journal): Response
    {
        Gate::authorize('manage', Site::class);

        return Inertia::render('admin/journals/edit', [
            'journal' => [
                'id' => $journal->id,
                'path' => $journal->path,
                'title' => $journal->title->get($journal->primary_locale, 'en') ?? '',
                'primaryLocale' => $journal->primary_locale,
                'isEnabled' => $journal->is_enabled,
                'aiEnabled' => (bool) $journal->setting('ai_enabled', config('ai.journal_default')),
            ],
            'locales' => Site::firstOrFail()->installed_locales ?? ['en'],
            'aiAvailable' => (bool) config('ai.enabled') && filled(config('services.anthropic.key')),
        ]);
    }

    public function update(Request $request, Journal $journal): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'path' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9-]*$/',
                Rule::unique('journals', 'path')->ignore($journal->id),
            ],
            'primary_locale' => ['required', 'string', 'max:10'],
            'is_enabled' => ['required', 'boolean'],
            // Deliberately `sometimes`, not `required`: an update that doesn't mention AI must
            // leave the journal's consent exactly as it was. Making it required would mean any
            // caller omitting the field silently revokes (or grants) consent as a side effect of
            // renaming a journal — the same partial-save data loss the CMS settings had.
            'ai_enabled' => ['sometimes', 'boolean'],
        ], [
            'path.regex' => 'The path may only contain lowercase letters, numbers and hyphens, and must start with a letter or number.',
        ]);

        // Preserve translations for other locales — only the primary-locale title is editable here.
        $title = $journal->title->toArray();
        $title[$data['primary_locale']] = $data['title'];

        $journal->update([
            'path' => $data['path'],
            'title' => $title,
            'primary_locale' => $data['primary_locale'],
            'is_enabled' => $data['is_enabled'],
        ]);

        // The per-journal AI consent switch (CLAUDE.md §6 guardrail 2). Stored as a journal
        // setting rather than a column because it is policy, not identity — and because
        // AiRunner reads it through Journal::setting() alongside the other operational toggles.
        if (array_key_exists('ai_enabled', $data)) {
            JournalSetting::updateOrCreate(
                ['journal_id' => $journal->id, 'name' => 'ai_enabled', 'locale' => ''],
                ['value' => $data['ai_enabled']],
            );
        }

        return redirect()->route('admin.journals.index')->with('success', 'Journal updated.');
    }

    /** Suspended and anonymized accounts can't be handed a journal to run. */
    private function assignableUsers(): Collection
    {
        return User::whereNull('anonymized_at')
            ->where(fn ($q) => $q->whereNull('is_suspended')->orWhere('is_suspended', false))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    }
}
