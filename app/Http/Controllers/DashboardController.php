<?php

namespace Alpha\Journal\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Site-wide landing page. A user's actual work happens inside a single journal's
     * scoped dashboard (Journal\DashboardController) — this page just lists the
     * journals they hold a role on so they can get there (CLAUDE.md §13.4).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $journals = $user->journalRoles()
            ->with('journal')
            ->get()
            ->pluck('journal')
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn ($journal) => [
                'path' => $journal->path,
                'title' => $journal->title->get($journal->primary_locale, 'en') ?? $journal->path,
            ]);

        return Inertia::render('dashboard', [
            'journals' => $journals,
        ]);
    }
}
