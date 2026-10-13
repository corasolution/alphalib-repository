<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\JournalSetting;
use Alpha\Journal\Support\JournalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A journal's identity as a publication: who publishes it, its ISSNs, and how its DOIs are formed.
 *
 * Every one of these was already read by code — DoajArticleBuilder wants the ISSNs, Crossref and
 * DataCite want the publisher, DoiPatternGenerator wants the prefix — but nothing anywhere could
 * write them, so they were permanently empty and the integrations that depend on them silently
 * degraded. This is the missing half.
 *
 * Stored as journal_settings rather than columns because they are editorial configuration, not
 * identity: a journal exists and functions without an ISSN, and gains one later.
 */
class JournalSettingsController extends Controller
{
    /**
     * The settings this screen owns. Anything not listed is left untouched by a save, so the
     * workflow timings and the AI consent flag cannot be clobbered from here.
     */
    private const FIELDS = ['publisher', 'issn_online', 'issn_print', 'doi_prefix', 'doi_pattern', 'doaj_api_key'];

    /** Settings stored as booleans rather than strings, so an unchecked box persists as false. */
    private const FLAGS = ['use_screening_stage', 'require_plagiarism_check'];

    public function edit(): Response
    {
        $journal = app(JournalContext::class)->requireJournal();

        Gate::authorize('manageSettings', $journal);

        return Inertia::render('journal/settings', [
            'journal' => [
                'path' => $journal->path,
                'title' => $journal->title->get($journal->primary_locale, 'en') ?? $journal->path,
            ],
            'settings' => collect(self::FIELDS)
                ->mapWithKeys(fn (string $field) => [$field => (string) ($journal->setting($field) ?? '')]),
            'flags' => collect(self::FLAGS)
                ->mapWithKeys(fn (string $flag) => [$flag => (bool) $journal->setting($flag, false)]),
            // Shown beside the pattern field so an editor can see what it produces before saving.
            'defaultPattern' => '{prefix}/{journalPath}.v{volume}.i{issue}.a{articleId}',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $journal = app(JournalContext::class)->requireJournal();

        Gate::authorize('manageSettings', $journal);

        $data = $request->validate([
            'publisher' => ['nullable', 'string', 'max:255'],
            // ISSN is eight digits as two groups, with X permitted as the final check digit.
            'issn_online' => ['nullable', 'string', 'regex:/^\d{4}-\d{3}[\dXx]$/'],
            'issn_print' => ['nullable', 'string', 'regex:/^\d{4}-\d{3}[\dXx]$/'],
            // A DOI prefix is always "10." followed by a registrant code.
            'doi_prefix' => ['nullable', 'string', 'regex:/^10\.\d{4,9}$/'],
            'doi_pattern' => ['nullable', 'string', 'max:255'],
            // Per journal, not per installation: each accepted journal has its own DOAJ account.
            'doaj_api_key' => ['nullable', 'string', 'max:255'],
            'use_screening_stage' => ['boolean'],
            'require_plagiarism_check' => ['boolean'],
        ], [
            'issn_online.regex' => 'An ISSN looks like 2789-1234 — eight digits, hyphenated, with X allowed as the last character.',
            'issn_print.regex' => 'An ISSN looks like 2789-1234 — eight digits, hyphenated, with X allowed as the last character.',
            'doi_prefix.regex' => 'A DOI prefix looks like 10.5555 — the registrant code your Crossref or DataCite membership issued.',
        ]);

        foreach (self::FIELDS as $field) {
            $value = trim((string) ($data[$field] ?? ''));

            if ($value === '') {
                // Deleted rather than stored empty, so Journal::setting() falls back to its default
                // and DoiPatternGenerator sees a genuinely absent prefix instead of an empty string.
                JournalSetting::where('journal_id', $journal->id)->where('name', $field)->where('locale', '')->delete();

                continue;
            }

            JournalSetting::updateOrCreate(
                ['journal_id' => $journal->id, 'name' => $field, 'locale' => ''],
                ['value' => $field === 'doi_prefix' || str_starts_with($field, 'issn') ? strtoupper($value) : $value],
            );
        }

        foreach (self::FLAGS as $flag) {
            // Stored either way, never deleted: an explicit false is the journal saying it does
            // not screen, which must not read back as "never configured".
            JournalSetting::updateOrCreate(
                ['journal_id' => $journal->id, 'name' => $flag, 'locale' => ''],
                ['value' => $request->boolean($flag) ? '1' : '0'],
            );
        }

        return back()->with('success', 'Journal settings saved.');
    }
}
