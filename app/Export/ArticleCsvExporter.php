<?php

namespace Alpha\Journal\Export;

use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Scopes\JournalScope;

/**
 * Exportable CSV for annual reports and DOAJ/accreditation submissions (CLAUDE.md §11.7).
 */
class ArticleCsvExporter
{
    public function export(Journal $journal): string
    {
        $locale = $journal->primary_locale;

        $publications = Publication::where('status', 'published')
            ->whereHas('submission', fn ($q) => $q->withoutGlobalScope(JournalScope::class)->where('journal_id', $journal->id))
            ->with(['submission.section', 'submission.issue', 'contributors'])
            ->orderBy('published_at')
            ->get();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['DOI', 'Title', 'Authors', 'Section', 'Volume', 'Issue', 'Year', 'Published Date']);

        foreach ($publications as $publication) {
            $submission = $publication->submission;
            $authors = $publication->contributors
                ->map(fn ($c) => trim("{$c->given_name} {$c->family_name}"))
                ->implode('; ');

            fputcsv($handle, [
                $publication->doi,
                $publication->title->get($locale, 'en'),
                $authors,
                $submission->section?->title?->get($locale, 'en'),
                $submission->issue?->volume,
                $submission->issue?->number,
                $publication->published_at?->format('Y'),
                $publication->published_at?->toDateString(),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
