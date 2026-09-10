<?php

namespace Alpha\Journal\Dashboard;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * "Editor home is a ranked queue... not a submission grid" (CLAUDE.md §13.4). Assumes it's
 * called from inside a request already scoped by the `journal` middleware — the Submission
 * queries below rely on the ambient JournalScope, not an explicit journal_id bypass, since this
 * is a page-load path, not a cross-journal batch job (contrast with ReviewerPerformanceCalculator,
 * which explicitly bypasses the scope because it isn't).
 *
 * "The full filterable table still exists, one click away" (§13.4) — submissionsFor() backs
 * that table with the exact same predicates as the counts above it, so a card's number and what
 * you land on after clicking it never disagree.
 */
class JournalActionQueue
{
    /** @var string[] valid `filter` query values for submissionsFor(), in queue display order */
    public const FILTERS = [
        'needs_decision',
        'reviews_overdue',
        'awaiting_reviewer_response',
        'no_editor_assigned',
        'ready_to_publish',
    ];

    /** @return array{needsDecision: int, reviewsOverdue: int, awaitingReviewerResponse: int, noEditorAssigned: int, readyToPublish: int} */
    public function forUser(Journal $journal, User $user): array
    {
        return [
            'needsDecision' => $this->needsDecisionQuery($journal, $user)->count(),
            'reviewsOverdue' => $this->reviewsOverdueQuery()->count(),
            'awaitingReviewerResponse' => $this->awaitingReviewerResponseQuery()->count(),
            'noEditorAssigned' => $this->noEditorAssignedQuery()->count(),
            'readyToPublish' => $this->readyToPublishQuery()->count(),
        ];
    }

    /**
     * Submissions for the full filterable table (§13.4). A null/empty filter is "everything in
     * this journal" — the table one click away from the queue, not the queue itself.
     */
    public function submissionsFor(?string $filter, Journal $journal, User $user): Builder
    {
        return match ($filter) {
            null, '' => Submission::query(),
            'needs_decision' => $this->needsDecisionQuery($journal, $user),
            'reviews_overdue' => $this->reviewsOverdueQuery(),
            'awaiting_reviewer_response' => $this->awaitingReviewerResponseQuery(),
            'no_editor_assigned' => $this->noEditorAssignedQuery(),
            'ready_to_publish' => $this->readyToPublishQuery(),
            default => throw new InvalidArgumentException("Unknown action queue filter [{$filter}]."),
        };
    }

    private function needsDecisionQuery(Journal $journal, User $user): Builder
    {
        $query = Submission::whereNotIn('stage', [SubmissionStage::Published->value, SubmissionStage::Declined->value]);

        if (! $user->hasRoleOn($journal, JournalRole::Editor)) {
            $query->whereHas('stageAssignments', fn ($q) => $q->where('user_id', $user->id)
                ->whereIn('role', [JournalRole::SectionEditor->value, JournalRole::GuestEditor->value]));
        }

        return $query;
    }

    private function reviewsOverdueQuery(): Builder
    {
        return Submission::whereHas('reviewRounds.reviewAssignments', fn ($q) => $q
            ->whereNull('completed_at')
            ->whereNull('declined_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()))
            ->distinct();
    }

    private function awaitingReviewerResponseQuery(): Builder
    {
        return Submission::whereHas('reviewRounds.reviewAssignments', fn ($q) => $q
            ->whereNull('responded_at')
            ->whereNull('declined_at')
            ->whereNull('completed_at')
            ->where(fn ($q2) => $q2->whereNull('due_at')->orWhere('due_at', '>=', now())))
            ->distinct();
    }

    private function noEditorAssignedQuery(): Builder
    {
        return Submission::where('stage', SubmissionStage::Submission->value)
            ->whereDoesntHave('stageAssignments', fn ($q) => $q->whereIn('role', [
                JournalRole::Editor->value, JournalRole::SectionEditor->value, JournalRole::GuestEditor->value,
            ]));
    }

    private function readyToPublishQuery(): Builder
    {
        return Submission::where('stage', SubmissionStage::Production->value)
            ->whereNotNull('issue_id')
            ->whereNotNull('current_publication_id');
    }
}
