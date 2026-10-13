<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\JournalSetting;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Workflow\Exceptions\WorkflowAuthorizationException;
use Alpha\Journal\Workflow\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reserving the final sign-off for a named role.
 *
 * Two-person approval on its own says only "a second editor", which is right for a journal of
 * peers and wrong for one with a masthead, where acceptance is the editor-in-chief's call. These
 * tests pin down the half that matters: that naming a role actually excludes everyone else, and
 * that a misconfigured journal fails closed rather than degrading back to "anyone".
 */
class FinalApprovalRoleTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $editorA;

    private User $editorB;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'BIU Journal']);
        $this->journal = Journal::create([
            'site_id' => $site->id, 'path' => 'biu', 'title' => ['en' => 'BIU Journal of Research'],
            'primary_locale' => 'en', 'is_enabled' => true,
        ]);

        $this->setting('require_dual_approval', '1');

        $this->editorA = $this->userWith(JournalRole::Editor);
        $this->editorB = $this->userWith(JournalRole::Editor);
        $this->manager = $this->userWith(JournalRole::JournalManager);
        // The manager also needs decision authority to act at all.
        $this->manager->journalRoles()->create(['journal_id' => $this->journal->id, 'role' => JournalRole::Editor->value]);
    }

    private function userWith(JournalRole $role): User
    {
        $user = User::factory()->create();
        $user->journalRoles()->create(['journal_id' => $this->journal->id, 'role' => $role->value]);

        return $user;
    }

    private function setting(string $name, ?string $value): void
    {
        JournalSetting::updateOrCreate(
            ['journal_id' => $this->journal->id, 'name' => $name, 'locale' => ''],
            ['value' => $value],
        );
    }

    private function submissionInReview(): Submission
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => SubmissionStage::Review->value,
            'submitted_at' => now(),
        ]);

        $submission->stageAssignments()->create([
            'user_id' => $this->editorA->id,
            'stage' => SubmissionStage::Review->value,
            'role' => JournalRole::Editor->value,
        ]);

        return $submission;
    }

    private function propose(Submission $submission): void
    {
        app(SubmissionWorkflow::class)->recordDecision($submission, $this->editorA, EditorialDecisionType::Accept);
    }

    private function confirm(Submission $submission, User $actor): void
    {
        app(SubmissionWorkflow::class)->recordDecision($submission->refresh(), $actor, EditorialDecisionType::Accept);
    }

    public function test_a_proposal_alone_does_not_move_the_submission(): void
    {
        $submission = $this->submissionInReview();

        $this->propose($submission);

        $this->assertSame(SubmissionStage::Review, $submission->refresh()->stage);
    }

    public function test_without_a_named_role_any_second_editor_can_confirm(): void
    {
        // The existing behaviour, unchanged — the regression guard for journals already running.
        $submission = $this->submissionInReview();
        $this->propose($submission);

        $this->confirm($submission, $this->editorB);

        $this->assertSame(SubmissionStage::Copyediting, $submission->refresh()->stage);
    }

    public function test_naming_a_role_excludes_a_second_editor_who_does_not_hold_it(): void
    {
        $this->setting('final_approval_role', JournalRole::JournalManager->value);
        $submission = $this->submissionInReview();
        $this->propose($submission);

        try {
            $this->confirm($submission, $this->editorB);
            $this->fail('A plain editor confirmed a decision reserved for the Journal Manager.');
        } catch (WorkflowAuthorizationException $e) {
            $this->assertStringContainsString('Journal Manager', $e->getMessage());
        }

        // And the submission stayed put.
        $this->assertSame(SubmissionStage::Review, $submission->refresh()->stage);
    }

    public function test_the_named_role_can_confirm(): void
    {
        $this->setting('final_approval_role', JournalRole::JournalManager->value);
        $submission = $this->submissionInReview();
        $this->propose($submission);

        $this->confirm($submission, $this->manager);

        $this->assertSame(SubmissionStage::Copyediting, $submission->refresh()->stage);
    }

    public function test_nobody_confirms_their_own_decision(): void
    {
        // Including the named approver — a reserved sign-off that one person can both propose and
        // grant is not a second pair of eyes.
        $this->setting('final_approval_role', JournalRole::Editor->value);
        $submission = $this->submissionInReview();
        $this->propose($submission);

        app(SubmissionWorkflow::class)->recordDecision($submission->refresh(), $this->editorA, EditorialDecisionType::Accept);

        $this->assertSame(SubmissionStage::Review, $submission->refresh()->stage);
        $this->assertSame(2, $submission->editorialDecisions()->where('decision', 'accept')->count());
    }

    public function test_a_nonsense_role_fails_closed(): void
    {
        // Only reachable by editing the database directly — validation blocks it on the way in.
        // It must not silently degrade to "anyone can approve".
        $this->setting('final_approval_role', 'chief-wizard');
        $submission = $this->submissionInReview();
        $this->propose($submission);

        $this->expectException(WorkflowAuthorizationException::class);

        $this->confirm($submission, $this->manager);
    }

    public function test_the_role_constrains_decline_as_well_as_accept(): void
    {
        $this->setting('final_approval_role', JournalRole::JournalManager->value);
        $submission = $this->submissionInReview();

        app(SubmissionWorkflow::class)->recordDecision($submission, $this->editorA, EditorialDecisionType::Decline);
        $this->assertSame(SubmissionStage::Review, $submission->refresh()->stage);

        app(SubmissionWorkflow::class)->recordDecision($submission->refresh(), $this->manager, EditorialDecisionType::Decline);
        $this->assertSame(SubmissionStage::Declined, $submission->refresh()->stage);
    }

    public function test_it_does_nothing_when_dual_approval_is_off(): void
    {
        // Naming an approver is meaningless without a countersign step; a single decision still
        // applies immediately and is not silently blocked.
        $this->setting('require_dual_approval', '0');
        $this->setting('final_approval_role', JournalRole::JournalManager->value);
        $submission = $this->submissionInReview();

        $this->propose($submission);

        $this->assertSame(SubmissionStage::Copyediting, $submission->refresh()->stage);
    }
}
