<?php

namespace Alpha\Journal\Policies;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Policies\Concerns\DeniesAuditorWrites;

class SubmissionPolicy
{
    use DeniesAuditorWrites;

    public function before(User $user, string $ability, ?Submission $submission = null): ?bool
    {
        if ($user->is_site_admin) {
            return true;
        }

        return $submission ? $this->denyAuditorWrite($user, $submission->journal, $ability) : null;
    }

    public function view(User $user, Submission $submission): bool
    {
        if ($submission->submitted_by === $user->id) {
            return true;
        }

        if ($user->hasRoleOn($submission->journal, JournalRole::Editor)) {
            return true;
        }

        return $submission->stageAssignments()->where('user_id', $user->id)->exists();
    }

    /**
     * Recording a decision — the actual authority/transition legality/dual-approval logic lives
     * in SubmissionWorkflow (CLAUDE.md §4.1: "enforce in the state machine, not the UI"). This
     * gate is the coarse HTTP-layer check; SubmissionWorkflow is the source of truth.
     */
    public function recordDecision(User $user, Submission $submission): bool
    {
        if ($user->hasRoleOn($submission->journal, JournalRole::Editor)) {
            return true;
        }

        return $user->hasRoleOn($submission->journal, JournalRole::SectionEditor)
            && $submission->stageAssignments()
                ->where('user_id', $user->id)
                ->where('role', JournalRole::SectionEditor->value)
                ->exists();
    }

    /**
     * Publish is separate, higher-privilege from accept (§4.1) — Editor or Journal Manager only.
     */
    public function publish(User $user, Submission $submission): bool
    {
        return $user->hasRoleOn($submission->journal, JournalRole::Editor)
            || $user->hasRoleOn($submission->journal, JournalRole::JournalManager);
    }
}
