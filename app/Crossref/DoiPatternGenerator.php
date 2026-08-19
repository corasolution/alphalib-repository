<?php

namespace Alpha\Journal\Crossref;

use Alpha\Journal\Models\Publication;
use InvalidArgumentException;

/**
 * DOI patterns: 10.PREFIX/journalPath.vVOL.iISS.aID, configurable per journal (CLAUDE.md §5.2).
 * `doi_prefix` (e.g. "10.5555") is required per journal_settings; `doi_pattern` overrides the
 * default template.
 */
class DoiPatternGenerator
{
    private const DEFAULT_PATTERN = '{prefix}/{journalPath}.v{volume}.i{issue}.a{articleId}';

    public function generate(Publication $publication): string
    {
        $submission = $publication->submission;
        $journal = $submission->journal;
        $issue = $submission->issue;

        $prefix = $journal->setting('doi_prefix');

        if (! $prefix) {
            throw new InvalidArgumentException("Journal [{$journal->path}] has no doi_prefix configured.");
        }

        $pattern = $journal->setting('doi_pattern', self::DEFAULT_PATTERN);

        return strtr($pattern, [
            '{prefix}' => $prefix,
            '{journalPath}' => $journal->path,
            '{volume}' => $issue?->volume ?? '0',
            '{issue}' => $issue?->number ?? '0',
            '{articleId}' => (string) $publication->id,
        ]);
    }
}
