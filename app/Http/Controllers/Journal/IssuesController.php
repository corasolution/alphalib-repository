<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Support\JournalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Issue assembly — OJS Production stage's "add the submission to a future or back issue" step.
 * Issues are created as drafts (is_published stays false, CLAUDE.md §2 versioned-publish
 * doctrine: nothing goes live until an explicit action) and published separately via `publish()`.
 */
class IssuesController extends Controller
{
    public function index(Request $request, string $journal): Response
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('view', $currentJournal);

        $locale = $currentJournal->primary_locale;

        return Inertia::render('journal/issues/index', [
            'journal' => ['path' => $currentJournal->path, 'title' => $currentJournal->title->get($locale, 'en') ?? $currentJournal->path],
            'issues' => Issue::where('journal_id', $currentJournal->id)
                ->orderByDesc('year')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($issue) => [
                    'id' => $issue->id,
                    'identification' => $issue->identification(),
                    'title' => $issue->title?->get($locale, 'en'),
                    'isPublished' => $issue->is_published,
                    'galleyCount' => $issue->galleys()->count(),
                    'submissionCount' => $issue->submissions()->count(),
                ]),
            'can' => ['manage' => Gate::allows('create', Issue::class)],
        ]);
    }

    public function create(Request $request, string $journal): Response
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('create', Issue::class);

        return Inertia::render('journal/issues/create', [
            'journal' => ['path' => $currentJournal->path, 'title' => $currentJournal->title->get($currentJournal->primary_locale, 'en') ?? $currentJournal->path],
        ]);
    }

    public function store(Request $request, string $journal): RedirectResponse
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('create', Issue::class);

        $data = $request->validate([
            'volume' => ['nullable', 'integer', 'min:1'],
            'number' => ['nullable', 'integer', 'min:1'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $issue = Issue::create([
            'journal_id' => $currentJournal->id,
            'volume' => $data['volume'] ?? null,
            'number' => $data['number'] ?? null,
            'year' => $data['year'] ?? null,
            'title' => isset($data['title']) ? [$currentJournal->primary_locale => $data['title']] : null,
        ]);

        return redirect()
            ->route('journal.issues.show', ['journal' => $currentJournal->path, 'issue' => $issue->id])
            ->with('success', 'Issue created.');
    }

    public function show(Request $request, string $journal, Issue $issue): Response
    {
        Gate::authorize('view', app(JournalContext::class)->requireJournal());

        $currentJournal = $issue->journal;
        $locale = $currentJournal->primary_locale;

        return Inertia::render('journal/issues/show', [
            'journal' => ['path' => $currentJournal->path, 'title' => $currentJournal->title->get($locale, 'en') ?? $currentJournal->path],
            'issue' => [
                'id' => $issue->id,
                'identification' => $issue->identification(),
                'title' => $issue->title?->get($locale, 'en'),
                'isPublished' => $issue->is_published,
                'publishAt' => $issue->publish_at,
            ],
            'galleys' => $issue->galleys->map(fn ($galley) => [
                'id' => $galley->id,
                'label' => $galley->label,
                'originalName' => $galley->original_name,
            ]),
            'submissions' => $issue->submissions()->with('currentPublication')->get()->map(fn ($submission) => [
                'id' => $submission->id,
                'stage' => $submission->stage->label(),
                'title' => $submission->currentPublication?->title?->get($locale, 'en') ?? "Submission #{$submission->id}",
            ]),
            'can' => ['manage' => Gate::allows('manage', $issue)],
        ]);
    }

    public function publish(Request $request, string $journal, Issue $issue): RedirectResponse
    {
        Gate::authorize('manage', $issue);

        $issue->update(['is_published' => true, 'publish_at' => $issue->publish_at ?? now()]);

        return back()->with('success', 'Issue published.');
    }
}
