<?php

namespace Alpha\Journal\Workflow;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Events\SubmissionStageChanged;
use Alpha\Journal\Models\EditorialDecision;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Support\Activity;
use Alpha\Journal\Workflow\Exceptions\WorkflowAuthorizationException;
use Alpha\Journal\Workflow\Exceptions\WorkflowTransitionException;

/**
 * Guarded transitions for the editorial workflow (CLAUDE.md §3, §4.1). Every transition:
 *  1. checks the actor has approval authority for this decision,
 *  2. checks the decision is legal from the submission's current stage,
 *  3. checks the current stage has at least one assigned editor,
 *  4. writes an immutable EditorialDecision row,
 *  5. applies the stage transition (unless awaiting a countersign),
 *  6. fires SubmissionStageChanged and logs to activity_log.
 *
 * Notifications and AI hooks subscribe to SubmissionStageChanged; they never call this service.
 */
class SubmissionWorkflow
{
    /** @var array<string, array{from: SubmissionStage[], to: SubmissionStage}> */
    private const TRANSITIONS = [
        'send_to_review' => ['from' => [SubmissionStage::Submission], 'to' => SubmissionStage::Review],
        'accept' => ['from' => [SubmissionStage::Review], 'to' => SubmissionStage::Copyediting],
        'send_to_copyediting' => ['from' => [SubmissionStage::Review], 'to' => SubmissionStage::Copyediting],
        'request_revisions' => ['from' => [SubmissionStage::Review], 'to' => SubmissionStage::Review],
        'resubmit' => ['from' => [SubmissionStage::Review], 'to' => SubmissionStage::Review],
        'decline' => [
            'from' => [SubmissionStage::Submission, SubmissionStage::Review, SubmissionStage::Copyediting, SubmissionStage::Production],
            'to' => SubmissionStage::Declined,
        ],
        'send_to_production' => ['from' => [SubmissionStage::Copyediting], 'to' => SubmissionStage::Production],
        'publish' => ['from' => [SubmissionStage::Production], 'to' => SubmissionStage::Published],
    ];

    /** @var array<string> decisions requiring a second editor's countersign under require_dual_approval */
    private const DUAL_APPROVAL_DECISIONS = ['accept', 'decline'];

    /**
     * Which decisions are legal from a given stage — used to render only valid actions in the
     * decision UI (§13.5) rather than let the workflow itself be the only place that rejects an
     * illegal choice after the fact.
     *
     * @return EditorialDecisionType[]
     */
    public function legalDecisionsFrom(SubmissionStage $stage): array
    {
        return array_values(array_filter(
            EditorialDecisionType::cases(),
            fn (EditorialDecisionType $decision) => in_array($stage, self::TRANSITIONS[$decision->value]['from'], true),
        ));
    }

    public function recordDecision(
        Submission $submission,
        User $actor,
        EditorialDecisionType $decision,
        ?string $reason = null,
    ): EditorialDecision {
        $roleUsed = $this->approvalRole($submission, $actor, $decision);
        $transition = $this->guardTransition($submission, $decision);
        $this->guardStageHasEditor($submission);
        $this->guardIssueAssignedForPublish($submission, $decision);
        $this->guardPublicationExistsForPublish($submission, $decision);

        if ($this->requiresDualApproval($submission, $decision)) {
            return $this->recordWithCountersign($submission, $actor, $decision, $roleUsed, $transition['to'], $reason);
        }

        $record = $submission->editorialDecisions()->create([
            'review_round_id' => $submission->currentReviewRound()?->id,
            'actor_id' => $actor->id,
            'role_used' => $roleUsed->value,
            'decision' => $decision->value,
            'target_stage' => $transition['to']->value,
            'reason' => $reason,
        ]);

        $this->applyTransition($submission, $decision, $transition['to'], $actor, $record);

        return $record;
    }

    private function recordWithCountersign(
        Submission $submission,
        User $actor,
        EditorialDecisionType $decision,
        JournalRole $roleUsed,
        SubmissionStage $toStage,
        ?string $reason,
    ): EditorialDecision {
        $pending = $submission->editorialDecisions()
            ->where('decision', $decision->value)
            ->where('requires_countersign', true)
            ->whereNull('countersigned_by')
            ->where('actor_id', '!=', $actor->id)
            ->latest('id')
            ->first();

        if ($pending) {
            $pending->countersign($actor);
            $this->applyTransition($submission, $decision, $toStage, $actor, $pending);

            return $pending->fresh();
        }

        return $submission->editorialDecisions()->create([
            'review_round_id' => $submission->currentReviewRound()?->id,
            'actor_id' => $actor->id,
            'role_used' => $roleUsed->value,
            'decision' => $decision->value,
            'target_stage' => $toStage->value,
            'reason' => $reason,
            'requires_countersign' => true,
        ]);
    }

    private function applyTransition(
        Submission $submission,
        EditorialDecisionType $decision,
        SubmissionStage $toStage,
        User $actor,
        EditorialDecision $record,
    ): void {
        $fromStage = $submission->stage->value;
        $submission->update(['stage' => $toStage->value]);

        $this->carryEditorsForward($submission, $fromStage, $toStage);

        if ($decision === EditorialDecisionType::Publish) {
            $publication = $submission->currentPublication;
            $publication->update([
                'status' => 'published',
                'published_at' => $publication->published_at ?? now(),
            ]);
        }

        SubmissionStageChanged::dispatch($submission, $decision, $record, $actor);

        Activity::log("submission.decision.{$decision->value}", $submission, $record->reason, [
            'from_stage' => $fromStage,
            'to_stage' => $toStage->value,
        ]);
    }

    /**
     * Moves the editorial assignments along with the submission.
     *
     * guardStageHasEditor() checks the CURRENT stage, so without this an editor lost authority
     * over a submission the instant they acted on it: the stage advanced, no assignment existed
     * there, and the very next decision threw "has no assigned editor". The people responsible
     * for a manuscript stay responsible for it as it moves.
     *
     * Only the roles that carry decision authority travel. A copyeditor assigned during
     * Copyediting has no business being auto-attached to Production, and the guard would not
     * count them anyway.
     *
     * Terminal stages are skipped — no decision is legal from Published or Declined, so an
     * assignment there would be a row nothing ever reads.
     */
    private function carryEditorsForward(Submission $submission, string $fromStage, SubmissionStage $toStage): void
    {
        if ($toStage->isTerminal() || $fromStage === $toStage->value) {
            return;
        }

        $editorialRoles = [
            JournalRole::Editor->value,
            JournalRole::SectionEditor->value,
            JournalRole::GuestEditor->value,
        ];

        $assignments = $submission->stageAssignments()
            ->where('stage', $fromStage)
            ->whereIn('role', $editorialRoles)
            ->get();

        foreach ($assignments as $assignment) {
            // firstOrCreate so a submission bounced back to a stage it has already visited does
            // not accumulate duplicate rows for the same person.
            $submission->stageAssignments()->firstOrCreate([
                'user_id' => $assignment->user_id,
                'stage' => $toStage->value,
                'role' => $assignment->role,
            ]);
        }
    }

    /**
     * @return array{from: SubmissionStage[], to: SubmissionStage}
     */
    private function guardTransition(Submission $submission, EditorialDecisionType $decision): array
    {
        $transition = self::TRANSITIONS[$decision->value];

        if (! in_array($submission->stage, $transition['from'], true)) {
            throw new WorkflowTransitionException(
                "Decision [{$decision->value}] is not legal from stage [{$submission->stage->value}]."
            );
        }

        return $transition;
    }

    private function guardStageHasEditor(Submission $submission): void
    {
        $hasEditor = $submission->stageAssignments()
            ->where('stage', $submission->stage->value)
            ->whereIn('role', [JournalRole::Editor->value, JournalRole::SectionEditor->value, JournalRole::GuestEditor->value])
            ->exists();

        if (! $hasEditor) {
            throw new WorkflowTransitionException(
                "Submission #{$submission->id} has no assigned editor on stage [{$submission->stage->value}]."
            );
        }
    }

    /**
     * "Require Editor or Journal Manager for publish, and require the issue to be assigned
     * first" (CLAUDE.md §4.1).
     */
    private function guardIssueAssignedForPublish(Submission $submission, EditorialDecisionType $decision): void
    {
        if ($decision === EditorialDecisionType::Publish && $submission->issue_id === null) {
            throw new WorkflowTransitionException(
                "Submission #{$submission->id} cannot be published without an assigned issue."
            );
        }
    }

    /**
     * There is nothing to publish without a Publication record (title, abstract, contributors) —
     * publish() marks it published (status + published_at) in applyTransition(), which would
     * silently do nothing useful without this guard.
     */
    private function guardPublicationExistsForPublish(Submission $submission, EditorialDecisionType $decision): void
    {
        if ($decision === EditorialDecisionType::Publish && $submission->current_publication_id === null) {
            throw new WorkflowTransitionException(
                "Submission #{$submission->id} cannot be published without a publication record."
            );
        }
    }

    /**
     * Editor (journal-wide), Section Editor (assigned to this submission), and Guest Editor
     * (assigned to the submission's issue, once one is assigned) can record a decision; publish
     * additionally requires Editor or Journal Manager specifically — Guest Editor cannot publish
     * even on their own issue (CLAUDE.md §4.1).
     */
    private function approvalRole(Submission $submission, User $actor, EditorialDecisionType $decision): JournalRole
    {
        $journal = $submission->journal;

        if ($decision->requiresPublishAuthority()) {
            if ($actor->hasRoleOn($journal, JournalRole::JournalManager)) {
                return JournalRole::JournalManager;
            }

            if ($actor->hasRoleOn($journal, JournalRole::Editor)) {
                return JournalRole::Editor;
            }

            throw new WorkflowAuthorizationException('Publishing requires the Editor or Journal Manager role.');
        }

        if ($actor->hasRoleOn($journal, JournalRole::Editor)) {
            return JournalRole::Editor;
        }

        $isAssignedSectionEditor = $actor->hasRoleOn($journal, JournalRole::SectionEditor)
            && $submission->stageAssignments()
                ->where('user_id', $actor->id)
                ->where('role', JournalRole::SectionEditor->value)
                ->exists();

        if ($isAssignedSectionEditor) {
            return JournalRole::SectionEditor;
        }

        $isAssignedGuestEditor = $actor->hasRoleOn($journal, JournalRole::GuestEditor)
            && $submission->issue_id !== null
            && $submission->issue->isEditedBy($actor);

        if ($isAssignedGuestEditor) {
            return JournalRole::GuestEditor;
        }

        throw new WorkflowAuthorizationException(
            "{$actor->name} has no editorial role on this journal, so they cannot record decisions "
            .'on it. Grant an Editor role in Journal → People, or ask an assigned editor to decide.'
        );
    }

    private function requiresDualApproval(Submission $submission, EditorialDecisionType $decision): bool
    {
        if (! in_array($decision->value, self::DUAL_APPROVAL_DECISIONS, true)) {
            return false;
        }

        return (bool) $submission->journal->setting('require_dual_approval', false);
    }
}
