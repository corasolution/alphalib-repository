<?php

namespace Alpha\Journal\Reviewers;

use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Models\Scopes\JournalScope;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;

class ReviewerPerformanceCalculator
{
    /**
     * $journal is explicit and trusted here, so we bypass JournalScope directly rather than
     * relying on ambient JournalContext — the caller may not be inside a request scoped to this
     * journal (e.g. a reviewer-matching batch job comparing candidates across none/any journal).
     */
    public function forUserOnJournal(User $user, Journal $journal): ReviewerPerformance
    {
        $submissionIds = Submission::withoutGlobalScope(JournalScope::class)
            ->where('journal_id', $journal->id)
            ->pluck('id');

        $assignments = ReviewAssignment::whereHas(
            'reviewRound',
            fn ($query) => $query->whereIn('submission_id', $submissionIds)
        )
            ->where('reviewer_id', $user->id)
            ->whereNotNull('completed_at')
            ->get();

        $completedCount = $assignments->count();

        $turnarounds = $assignments
            ->filter(fn (ReviewAssignment $a) => $a->invited_at !== null)
            ->map(fn (ReviewAssignment $a) => $a->invited_at->diffInDays($a->completed_at));

        $onTime = $assignments->filter(
            fn (ReviewAssignment $a) => $a->due_at === null || $a->completed_at->lessThanOrEqualTo($a->due_at)
        );

        $rated = $assignments->whereNotNull('quality_rating');

        return new ReviewerPerformance(
            completedCount: $completedCount,
            averageTurnaroundDays: $turnarounds->isEmpty() ? null : round($turnarounds->avg(), 1),
            onTimeRate: $completedCount === 0 ? null : round($onTime->count() / $completedCount, 2),
            averageQualityRating: $rated->isEmpty() ? null : round($rated->avg('quality_rating'), 1),
        );
    }
}
