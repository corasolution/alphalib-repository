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

        // A Journal Manager may assign editors (assignEditor) and publish (publish), yet could not
        // open the submission to do either — every one of those actions is reached from this page.
        // On a journal staffed only by a manager, which is an ordinary small-journal setup, that
        // locked the person who runs it out of their own submissions entirely. The existing tests
        // missed it because they post to the decision endpoints directly, never opening the page.
        if ($user->hasRoleOn($submission->journal, JournalRole::JournalManager)) {
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
        $journal = $submission->journal;

        if ($user->hasRoleOn($journal, JournalRole::Editor)) {
            return true;
        }

        // A Journal Manager is authorised to publish (§4.1, and both SubmissionPolicy::publish()
        // and SubmissionWorkflow::approvalRole() say so) — but publish goes through this coarse
        // gate first, so leaving them out here 403'd the person who runs the journal out of the
        // one decision they are specifically empowered to make. The workflow still rejects them
        // for every OTHER decision, which is where that distinction belongs.
        if ($user->hasRoleOn($journal, JournalRole::JournalManager)) {
            return true;
        }

        // Assigned Section Editor or Guest Editor. approvalRole() accepts both; omitting Guest
        // Editor here would deny them at the door despite the workflow being ready to accept them.
        return $submission->stageAssignments()
            ->where('user_id', $user->id)
            ->whereIn('role', [JournalRole::SectionEditor->value, JournalRole::GuestEditor->value])
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

    /**
     * Handing out editorial responsibility for a submission.
     *
     * Deliberately narrower than recordDecision: a Section Editor can hold an assignment and act
     * on it, but cannot hand assignments to anyone — including themselves, which would otherwise
     * be a route to authority over any submission on the journal.
     */
    public function assignEditor(User $user, Submission $submission): bool
    {
        return $user->hasRoleOn($submission->journal, JournalRole::Editor)
            || $user->hasRoleOn($submission->journal, JournalRole::JournalManager);
    }

    /**
     * Same authority shape as recordDecision (§4.1) — inviting a reviewer is editorial work on
     * the submission, not a separate higher-privilege action the way publish is.
     */
    public function assignReviewer(User $user, Submission $submission): bool
    {
        return $this->recordDecision($user, $submission);
    }

    /**
     * Uploading a new file version — the copyediting file exchange (§3, OJS Copyediting stage:
     * "improved by the work of a copyeditor... authors can be given the opportunity to review the
     * copyedits") and an author's revised-manuscript re-upload after a request_revisions decision
     * are the same action from the file-storage model's point of view (SubmissionFile::store*
     * doesn't care why a new version showed up). Editors, an assigned Copyeditor, and the
     * submitting author can all upload; a stranger or unassigned reviewer cannot.
     */
    public function uploadFile(User $user, Submission $submission): bool
    {
        if ($submission->submitted_by === $user->id) {
            return true;
        }

        if ($this->recordDecision($user, $submission)) {
            return true;
        }

        return $user->hasRoleOn($submission->journal, JournalRole::Copyeditor)
            && $submission->stageAssignments()
                ->where('user_id', $user->id)
                ->where('role', JournalRole::Copyeditor->value)
                ->exists();
    }
}
