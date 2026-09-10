<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Dashboard\JournalActionQueue;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Support\JournalContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, string $journal, JournalActionQueue $queue): Response
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('view', $currentJournal);

        $locale = $currentJournal->primary_locale;

        return Inertia::render('journal/dashboard', [
            'journal' => [
                'path' => $currentJournal->path,
                'title' => $currentJournal->title->get($locale, 'en') ?? $currentJournal->path,
            ],
            'queue' => $queue->forUser($currentJournal, $request->user()),
            'can' => ['manageUsers' => Gate::allows('manageUsers', $currentJournal)],
        ]);
    }
}
