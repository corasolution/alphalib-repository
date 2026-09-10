<?php

namespace Alpha\Journal\Http\Controllers;

use Alpha\Journal\Models\Journal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Site-wide landing page. A user's actual work happens inside a single journal's
     * scoped dashboard (Journal\DashboardController) — this page just lists the
     * journals they hold a role on so they can get there (CLAUDE.md §13.4).
     *
     * A site admin holds no per-journal role row by design (every JournalPolicy/SubmissionPolicy
     * before() hook grants them access without one) — so this list is every enabled journal for
     * them, not "none", or the one global role would be unreachable from its own landing page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $journals = $user->is_site_admin
            ? Journal::where('is_enabled', true)->get()
            : $user->journalRoles()->with('journal')->get()->pluck('journal')->filter()->unique('id')->values();

        return Inertia::render('dashboard', [
            'journals' => $journals->map(fn ($journal) => [
                'path' => $journal->path,
                'title' => $journal->title->get($journal->primary_locale, 'en') ?? $journal->path,
            ]),
        ]);
    }
}
