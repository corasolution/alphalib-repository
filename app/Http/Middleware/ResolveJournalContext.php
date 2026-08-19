<?php

namespace Alpha\Journal\Http\Middleware;

use Alpha\Journal\Models\Journal;
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
            ->where('is_enabled', true)
            ->first();

        if (! $journal) {
            throw new NotFoundHttpException("Journal [{$path}] not found.");
        }

        app(JournalContext::class)->set($journal);

        return $next($request);
    }
}
