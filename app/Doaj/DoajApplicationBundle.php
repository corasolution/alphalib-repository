<?php

namespace Alpha\Journal\Doaj;

use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Scopes\JournalScope;

/**
 * "Produce the DOAJ application metadata bundle — a report the journal manager can hand to DOAJ
 * during application" (CLAUDE.md §5.3, called out as "a real, unmet pain point"). A readiness
 * checklist against journal_settings, not just a data dump — tells the manager exactly what's
 * still missing, plus real publishing-turnaround stats computed from submission history.
 */
class DoajApplicationBundle
{
    /** @var array<string, string> */
    private const REQUIRED_SETTINGS = [
        'issn_online' => 'ISSN (online)',
        'issn_print' => 'ISSN (print)',
        'publisher' => 'Publisher',
        'country' => 'Country of publisher',
        'license_url' => 'License (e.g. CC BY 4.0) URL',
        'review_process' => 'Description of the peer review process',
        'plagiarism_screening' => 'Plagiarism screening policy',
        'apc_amount' => 'Article processing charge (amount, or "0" for none)',
        'preservation_policy' => 'Preservation policy',
    ];

    /** @return array<string, mixed> */
    public function generate(Journal $journal): array
    {
        $locale = $journal->primary_locale;

        $fields = [];
        foreach (self::REQUIRED_SETTINGS as $key => $label) {
            $value = $journal->setting($key);
            $fields[] = [
                'key' => $key,
                'label' => $label,
                'value' => $value,
                'configured' => $value !== null && $value !== '',
            ];
        }

        $missing = array_values(array_map(
            fn ($f) => $f['label'],
            array_filter($fields, fn ($f) => ! $f['configured'])
        ));

        return [
            'journal_title' => $journal->title->get($locale, 'en') ?? $journal->path,
            'journal_url' => rtrim(config('app.url'), '/')."/journal/{$journal->path}",
            'languages' => $journal->installed_locales,
            'fields' => $fields,
            'missing_fields' => $missing,
            'is_ready_to_apply' => empty($missing),
            'publishing_stats' => $this->publishingStats($journal),
        ];
    }

    /** @return array{published_article_count: int, average_days_submission_to_publication: float|null} */
    private function publishingStats(Journal $journal): array
    {
        $publications = Publication::where('status', 'published')
            ->whereHas('submission', fn ($q) => $q->withoutGlobalScope(JournalScope::class)->where('journal_id', $journal->id))
            ->with('submission')
            ->get();

        if ($publications->isEmpty()) {
            return ['published_article_count' => 0, 'average_days_submission_to_publication' => null];
        }

        $durations = $publications
            ->filter(fn (Publication $p) => $p->submission->submitted_at !== null && $p->published_at !== null)
            ->map(fn (Publication $p) => $p->submission->submitted_at->diffInDays($p->published_at));

        return [
            'published_article_count' => $publications->count(),
            'average_days_submission_to_publication' => $durations->isEmpty() ? null : round($durations->avg(), 1),
        ];
    }
}
