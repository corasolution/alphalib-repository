<?php

namespace Alpha\Journal\Reviewers;

/**
 * Reviewer performance record (CLAUDE.md §11.4): assignments completed, average days, on-time
 * rate, editor quality rating. Feeds A3 reviewer matching later — "editors pick reviewers who
 * actually respond" (§6, A3).
 */
final class ReviewerPerformance
{
    public function __construct(
        public readonly int $completedCount,
        public readonly ?float $averageTurnaroundDays,
        public readonly ?float $onTimeRate,
        public readonly ?float $averageQualityRating,
    ) {}
}
