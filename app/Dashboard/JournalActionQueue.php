<?php

namespace Alpha\Journal\Dashboard;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;

/**
 * "Editor home is a ranked queue... not a submission grid" (CLAUDE.md §13.4). Assumes it's
 * called from inside a request already scoped by the `journal` middleware — the Submission
 * queries below rely on the ambient JournalScope, not an explicit journal_id bypass, since this
 * is a page-load path, not a cross-journal batch job (contrast with ReviewerPerformanceCalculator,
 * which explicitly bypasses the scope because it isn't).
 */
class JournalActionQueue
{
    /** @return array{needsDecision: int, reviewsOverdue: int, awaitingReviewerResponse: int, noEditorAssigned: int, readyToPublish: int} */
    public function forUser(Journal $journal, User $user): array
    {
        return [
            'needsDecision' => $this->needsDecisionCount($journal, $user),
            'reviewsOverdue' => $this->reviewsOverdueCount(),
            'awaitingReviewerResponse' => $this->awaitingReviewerResponseCount(),
            'noEditorAssigned' => $this->noEditorAssignedCount(),
            'readyToPublish' => $this->readyToPublishCount(),
        ];
    }

    private function needsDecisionCount(Journal $journal, User $user): int
    {
        $query = Submission::whereNotIn('stage', [SubmissionStage::Published->value, SubmissionStage::Declined->value]);

        if (! $user->hasRoleOn($journal, JournalRole::Editor)) {
            $query->whereHas('stageAssignments', fn ($q) => $q->where('user_id', $user->id)
                ->whereIn('role', [JournalRole::SectionEditor->value, JournalRole::GuestEditor->value]));
        }

        return $query->count();
    }

    private function reviewsOverdueCount(): int
    {
        return ReviewAssignment::whereNull('completed_at')
            ->whereNull('declined_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereHas('reviewRound.submission')
            ->count();
    }

    private function awaitingReviewerResponseCount(): int
    {
        return ReviewAssignment::whereNull('responded_at')
            ->whereNull('declined_at')
            ->whereNull('completed_at')
            ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '>=', now()))
            ->whereHas('reviewRound.submission')
            ->count();
    }

    private function noEditorAssignedCount(): int
    {
        return Submission::where('stage', SubmissionStage::Submission->value)
            ->whereDoesntHave('stageAssignments', fn ($q) => $q->whereIn('role', [
                JournalRole::Editor->value, JournalRole::SectionEditor->value, JournalRole::GuestEditor->value,
            ]))
            ->count();
    }

    private function readyToPublishCount(): int
    {
        return Submission::where('stage', SubmissionStage::Production->value)
            ->whereNotNull('issue_id')
            ->whereNotNull('current_publication_id')
            ->count();
    }
}
