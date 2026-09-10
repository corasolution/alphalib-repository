<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Dashboard\JournalActionQueue;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Support\JournalContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The full filterable table (§13.4) — one click away from the ranked queue, never the landing
 * screen itself. `filter` reuses JournalActionQueue's own predicates so a queue card's count and
 * what this page shows after clicking it can never disagree.
 */
class SubmissionsIndexController extends Controller
{
    public function index(Request $request, string $journal, JournalActionQueue $queue): Response
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('view', $currentJournal);

        $data = $request->validate([
            'filter' => ['nullable', 'string', Rule::in(JournalActionQueue::FILTERS)],
        ]);

        $locale = $currentJournal->primary_locale;
        $filter = $data['filter'] ?? null;

        $submissions = $queue->submissionsFor($filter, $currentJournal, $request->user())
            ->with(['section', 'submittedBy', 'currentPublication'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('journal/submissions/index', [
            'journal' => [
                'path' => $currentJournal->path,
                'title' => $currentJournal->title->get($locale, 'en') ?? $currentJournal->path,
            ],
            'filter' => $filter,
            'submissions' => $submissions->through(fn ($submission) => [
                'id' => $submission->id,
                // Without this an editor scanning the list sees only "#3" and has to open every
                // row to find out what any of them are.
                'title' => $submission->currentPublication?->title?->get($locale, 'en'),
                'stage' => $submission->stage->value,
                'stageLabel' => $submission->stage->label(),
                'section' => $submission->section?->title?->get($locale, 'en'),
                'submittedBy' => $submission->submittedBy?->name,
                'submittedAt' => $submission->submitted_at,
            ]),
        ]);
    }
}
