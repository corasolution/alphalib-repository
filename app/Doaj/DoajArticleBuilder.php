<?php

namespace Alpha\Journal\Doaj;

use Alpha\Journal\Models\Publication;

/**
 * DOAJ API v4 article ("bibjson") payload (CLAUDE.md §5.3). Page numbers are deliberately
 * omitted — Publication doesn't model them (many OA journals are continuous-publication with no
 * page ranges anyway), and DOAJ doesn't require them.
 */
class DoajArticleBuilder
{
    /** @return array<string, mixed> */
    public function build(Publication $publication): array
    {
        $submission = $publication->submission;
        $journal = $submission->journal;
        $locale = $journal->primary_locale;
        $issue = $submission->issue;

        $authors = $publication->contributors->map(fn ($c) => array_filter([
            'name' => trim("{$c->given_name} {$c->family_name}"),
            'orcid_id' => $c->orcid ? "https://orcid.org/{$c->orcid}" : null,
            'affiliation' => $c->affiliation,
        ]))->all();

        $identifiers = array_values(array_filter([
            $publication->doi ? ['type' => 'doi', 'id' => $publication->doi] : null,
        ]));

        $issns = array_values(array_filter([
            $journal->setting('issn_online'),
            $journal->setting('issn_print'),
        ]));

        $bibjson = [
            'title' => $publication->title->get($locale, 'en') ?? '',
            'author' => $authors,
            'abstract' => $publication->abstract?->get($locale, 'en'),
            'identifier' => $identifiers,
            'journal' => array_filter([
                'title' => $journal->title->get($locale, 'en') ?? $journal->path,
                'volume' => $issue?->volume !== null ? (string) $issue->volume : null,
                'number' => $issue?->number !== null ? (string) $issue->number : null,
                'publisher' => $journal->setting('publisher'),
                'language' => [$locale],
                'issns' => $issns,
            ]),
            'link' => [[
                'type' => 'fulltext',
                'url' => rtrim(config('app.url'), '/')."/journal/{$journal->path}/articles/{$publication->id}",
                'content_type' => 'text/html',
            ]],
            'year' => $publication->published_at?->format('Y'),
            'month' => $publication->published_at?->format('n'),
        ];

        return ['bibjson' => array_filter($bibjson, fn ($v) => $v !== null && $v !== [])];
    }
}
