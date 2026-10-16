<?php

namespace Alpha\Journal\Support;

use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Scopes\JournalScope;

/**
 * What a journal has actually published: the current issue's contents and the archive behind it.
 *
 * Shared by the journal's own homepage and by the site homepage when an admin chooses to show a
 * single journal there, so the two cannot disagree about what counts as published. Takes the
 * journal explicitly and opts out of the ambient JournalScope — the site homepage has no journal
 * in context, and a scope that denies by default would otherwise return nothing.
 */
class JournalListing
{
    /**
     * @param  list<string>  $chain  locale fallback chain, reader's language first
     * @return array{currentIssue: ?array<string, mixed>, archive: list<array<string, mixed>>}
     */
    public static function for(Journal $journal, array $chain): array
    {
        // Newest first: the head of this list is the current issue, the tail is the archive.
        $issues = Issue::withoutGlobalScope(JournalScope::class)
            ->where('journal_id', $journal->id)
            ->where('is_published', true)
            ->orderByDesc('publish_at')
            ->get();

        $current = $issues->first();

        return [
            'currentIssue' => $current ? [
                'identification' => $current->identification(),
                'title' => $current->title?->get(...$chain),
                'publishedAt' => $current->publish_at?->toIso8601String(),
                'articles' => self::publishedArticles($journal, $current, $chain),
            ] : null,

            // Counted, not loaded — the archive shows how much is in each issue without pulling
            // every publication on the site.
            'archive' => $issues->skip(1)->map(fn (Issue $issue) => [
                'id' => $issue->id,
                'identification' => $issue->identification(),
                'title' => $issue->title?->get(...$chain),
                'articleCount' => Publication::publishedIn($journal, $issue)->count(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  list<string>  $chain
     * @return list<array{id: int, title: ?string, section: ?string, doi: ?string, galleyId: ?int, authors: string}>
     *
     * @see Publication::scopePublishedIn() for why status alone is not enough.
     */
    private static function publishedArticles(Journal $journal, Issue $issue, array $chain): array
    {
        return Publication::publishedIn($journal, $issue)
            ->with(['contributors', 'galleys', 'submission.section'])
            ->orderBy('published_at')
            ->get()
            ->map(fn (Publication $publication) => [
                'id' => $publication->id,
                'title' => $publication->title->get(...$chain),
                'section' => $publication->submission?->section?->title?->get(...$chain),
                'doi' => $publication->doi,
                // The first galley, so the listing can offer a direct download beside the title.
                'galleyId' => $publication->galleys->sortBy('sort_order')->first()?->id,
                'authors' => $publication->contributors
                    ->map(fn ($c) => trim("{$c->given_name} {$c->family_name}"))
                    ->implode(', '),
            ])
            ->values()
            ->all();
    }
}
