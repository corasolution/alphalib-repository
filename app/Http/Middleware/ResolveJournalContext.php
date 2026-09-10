<?php

namespace Alpha\Journal\Http\Middleware;

use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\User;
use Alpha\Journal\Support\JournalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves the {journal} route segment to a Journal and binds it into JournalContext for the
 * request — the only source of truth JournalScope trusts (CLAUDE.md §2).
 */
class ResolveJournalContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->route('journal');

        $journal = Journal::withoutGlobalScopes()
            ->where('path', $path)
            ->first();

        if (! $journal || ! $this->canReach($journal, $request->user())) {
            throw new NotFoundHttpException("Journal [{$path}] not found.");
        }

        app(JournalContext::class)->set($journal);

        return $next($request);
    }

    /**
     * A disabled journal is taken off the public web but must stay workable for the people
     * running it — otherwise disabling one strands its editors mid-workflow, with in-flight
     * submissions unreachable. Readers (and logged-in users with no role on it) get the same
     * 404 they'd get for a journal that never existed: "disabled" must not be discoverable.
     *
     * Public listings and the OAI provider filter is_enabled in their own queries, so they stay
     * correct regardless of what this allows.
     */
    private function canReach(Journal $journal, ?User $user): bool
    {
        if ($journal->is_enabled) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $user->is_site_admin
            || $user->journalRoles()->where('journal_id', $journal->id)->exists();
    }
}
