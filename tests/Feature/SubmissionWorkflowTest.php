<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Events\SubmissionStageChanged;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\IssueEditorAssignment;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Section;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Workflow\Exceptions\WorkflowAuthorizationException;
use Alpha\Journal\Workflow\Exceptions\WorkflowTransitionException;
use Alpha\Journal\Workflow\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SubmissionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $editor;

    private User $author;

    private SubmissionWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'a', 'title' => ['en' => 'Journal A']]);
        $this->editor = User::factory()->create();
        $this->author = User::factory()->create();
        $this->journal->grantRole($this->editor, JournalRole::Editor);
        $this->workflow = app(SubmissionWorkflow::class);
    }

    private function makeSubmission(SubmissionStage $stage = SubmissionStage::Submission): Submission
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->author->id,
            'stage' => $stage->value,
            'submitted_at' => now(),
        ]);

        StageAssignment::create([
            'submission_id' => $submission->id,
            'user_id' => $this->editor->id,
            'stage' => $stage->value,
            'role' => JournalRole::Editor->value,
        ]);

        return $submission;
    }

    public function test_send_to_review_moves_submission_from_submission_to_review(): void
    {
        $submission = $this->makeSubmission();

        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::SendToReview);

        $this->assertSame(SubmissionStage::Review, $submission->fresh()->stage);
    }

    public function test_accept_moves_submission_from_review_to_copyediting(): void
    {
        $submission = $this->makeSubmission(SubmissionStage::Review);

        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::Accept);

        $this->assertSame(SubmissionStage::Copyediting, $submission->fresh()->stage);
    }

    public function test_publish_moves_submission_from_production_to_published(): void
    {
        $submission = $this->makeSubmission(SubmissionStage::Production);
        $issue = Issue::create(['journal_id' => $this->journal->id, 'year' => 2026]);
        $submission->update(['issue_id' => $issue->id]);
        $publication = $submission->publications()->create(['title' => ['en' => 'A Title']]);
        $submission->update(['current_publication_id' => $publication->id]);

        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::Publish);

        $this->assertSame(SubmissionStage::Published, $submission->fresh()->stage);
        $this->assertSame('published', $publication->fresh()->status);
        $this->assertNotNull($publication->fresh()->published_at);
    }

    public function test_publish_is_blocked_without_an_assigned_issue(): void
    {
        $submission = $this->makeSubmission(SubmissionStage::Production);

        $this->expectException(WorkflowTransitionException::class);
        $this->expectExceptionMessageMatches('/without an assigned issue/');

        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::Publish);
    }

    public function test_publish_is_blocked_without_a_publication_record(): void
    {
        $submission = $this->makeSubmission(SubmissionStage::Production);
        $issue = Issue::create(['journal_id' => $this->journal->id, 'year' => 2026]);
        $submission->update(['issue_id' => $issue->id]);

        $this->expectException(WorkflowTransitionException::class);
        $this->expectExceptionMessageMatches('/without a publication record/');

        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::Publish);
    }

    public function test_decline_is_legal_from_every_non_terminal_stage(): void
    {
        foreach ([SubmissionStage::Submission, SubmissionStage::Review, SubmissionStage::Copyediting, SubmissionStage::Production] as $stage) {
            $submission = $this->makeSubmission($stage);

            $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::Decline);

            $this->assertSame(SubmissionStage::Declined, $submission->fresh()->stage);
        }
    }

    public function test_illegal_transitions_are_rejected(): void
    {
        // accept is only legal from Review, not Submission
        $submission = $this->makeSubmission(SubmissionStage::Submission);

        $this->expectException(WorkflowTransitionException::class);

        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::Accept);
    }

    public function test_publish_is_illegal_before_production(): void
    {
        $submission = $this->makeSubmission(SubmissionStage::Copyediting);

        $this->expectException(WorkflowTransitionException::class);

        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::Publish);
    }

    public function test_decisions_are_rejected_once_a_submission_is_in_a_terminal_stage(): void
    {
        $submission = $this->makeSubmission(SubmissionStage::Declined);

        $this->expectException(WorkflowTransitionException::class);

        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::SendToReview);
    }

    public function test_a_stage_with_no_assigned_editor_blocks_the_transition(): void
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value,
        ]);
        // Note: no StageAssignment created for this submission.

        $this->expectException(WorkflowTransitionException::class);
        $this->expectExceptionMessageMatches('/no assigned editor/');

        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::SendToReview);
    }

    public function test_an_author_has_no_decision_authority(): void
    {
        $submission = $this->makeSubmission();

        $this->expectException(WorkflowAuthorizationException::class);

        $this->workflow->recordDecision($submission, $this->author, EditorialDecisionType::SendToReview);
    }

    public function test_a_section_editor_not_assigned_to_the_submission_has_no_authority(): void
    {
        $submission = $this->makeSubmission();
        $sectionEditor = User::factory()->create();
        $this->journal->grantRole($sectionEditor, JournalRole::SectionEditor);
        // Not assigned via StageAssignment to this specific submission.

        $this->expectException(WorkflowAuthorizationException::class);

        $this->workflow->recordDecision($submission, $sectionEditor, EditorialDecisionType::SendToReview);
    }

    public function test_a_section_editor_assigned_to_the_submission_has_authority(): void
    {
        $submission = $this->makeSubmission();
        $sectionEditor = User::factory()->create();
        $this->journal->grantRole($sectionEditor, JournalRole::SectionEditor);
        StageAssignment::create([
            'submission_id' => $submission->id,
            'user_id' => $sectionEditor->id,
            'stage' => SubmissionStage::Submission->value,
            'role' => JournalRole::SectionEditor->value,
        ]);

        $this->workflow->recordDecision($submission, $sectionEditor, EditorialDecisionType::SendToReview);

        $this->assertSame(SubmissionStage::Review, $submission->fresh()->stage);
    }

    public function test_a_section_editor_cannot_publish(): void
    {
        $submission = $this->makeSubmission(SubmissionStage::Production);
        $sectionEditor = User::factory()->create();
        $this->journal->grantRole($sectionEditor, JournalRole::SectionEditor);
        StageAssignment::create([
            'submission_id' => $submission->id,
            'user_id' => $sectionEditor->id,
            'stage' => SubmissionStage::Production->value,
            'role' => JournalRole::SectionEditor->value,
        ]);

        $this->expectException(WorkflowAuthorizationException::class);

        $this->workflow->recordDecision($submission, $sectionEditor, EditorialDecisionType::Publish);
    }

    public function test_a_guest_editor_assigned_to_the_submissions_issue_has_authority(): void
    {
        $submission = $this->makeSubmission();
        $issue = Issue::create(['journal_id' => $this->journal->id, 'year' => 2026]);
        $submission->update(['issue_id' => $issue->id]);
        $guestEditor = User::factory()->create();
        $this->journal->grantRole($guestEditor, JournalRole::GuestEditor);
        IssueEditorAssignment::create(['issue_id' => $issue->id, 'user_id' => $guestEditor->id]);

        $this->workflow->recordDecision($submission, $guestEditor, EditorialDecisionType::SendToReview);

        $this->assertSame(SubmissionStage::Review, $submission->fresh()->stage);
    }

    public function test_a_guest_editor_not_assigned_to_the_issue_has_no_authority(): void
    {
        $submission = $this->makeSubmission();
        $issue = Issue::create(['journal_id' => $this->journal->id, 'year' => 2026]);
        $submission->update(['issue_id' => $issue->id]);
        $guestEditor = User::factory()->create();
        $this->journal->grantRole($guestEditor, JournalRole::GuestEditor);
        // Not assigned to this issue via IssueEditorAssignment.

        $this->expectException(WorkflowAuthorizationException::class);

        $this->workflow->recordDecision($submission, $guestEditor, EditorialDecisionType::SendToReview);
    }

    public function test_a_guest_editor_has_no_authority_before_an_issue_is_assigned(): void
    {
        $submission = $this->makeSubmission();
        $guestEditor = User::factory()->create();
        $this->journal->grantRole($guestEditor, JournalRole::GuestEditor);

        $this->expectException(WorkflowAuthorizationException::class);

        $this->workflow->recordDecision($submission, $guestEditor, EditorialDecisionType::SendToReview);
    }

    public function test_a_guest_editor_cannot_publish_even_on_their_own_issue(): void
    {
        $submission = $this->makeSubmission(SubmissionStage::Production);
        $issue = Issue::create(['journal_id' => $this->journal->id, 'year' => 2026]);
        $submission->update(['issue_id' => $issue->id]);
        $guestEditor = User::factory()->create();
        $this->journal->grantRole($guestEditor, JournalRole::GuestEditor);
        IssueEditorAssignment::create(['issue_id' => $issue->id, 'user_id' => $guestEditor->id]);

        $this->expectException(WorkflowAuthorizationException::class);

        $this->workflow->recordDecision($submission, $guestEditor, EditorialDecisionType::Publish);
    }

    public function test_dual_approval_requires_a_second_distinct_editor_before_transitioning(): void
    {
        $this->journal->settings()->create(['name' => 'require_dual_approval', 'value' => true]);
        $submission = $this->makeSubmission(SubmissionStage::Review);
        $secondEditor = User::factory()->create();
        $this->journal->grantRole($secondEditor, JournalRole::Editor);

        $firstDecision = $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::Accept);

        $this->assertSame(SubmissionStage::Review, $submission->fresh()->stage, 'stage must not advance on first signature');
        $this->assertTrue($firstDecision->isAwaitingCountersign());

        $this->workflow->recordDecision($submission, $secondEditor, EditorialDecisionType::Accept);

        $this->assertSame(SubmissionStage::Copyediting, $submission->fresh()->stage);
        $this->assertFalse($firstDecision->fresh()->isAwaitingCountersign());
    }

    public function test_dual_approval_rejects_a_self_countersign(): void
    {
        $this->journal->settings()->create(['name' => 'require_dual_approval', 'value' => true]);
        $submission = $this->makeSubmission(SubmissionStage::Review);

        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::Accept);

        // Same editor recording it again should just produce a second pending signature,
        // not self-countersign — the workflow only matches pending decisions by a *different* actor.
        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::Accept);

        $this->assertSame(SubmissionStage::Review, $submission->fresh()->stage);
        $this->assertSame(2, $submission->editorialDecisions()->count());
    }

    public function test_editorial_decision_rows_are_immutable_outside_of_countersign(): void
    {
        $submission = $this->makeSubmission();
        $decision = $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::SendToReview);

        $this->expectException(\LogicException::class);

        $decision->update(['reason' => 'tampered']);
    }

    public function test_stage_change_dispatches_an_event(): void
    {
        Event::fake();
        $submission = $this->makeSubmission();

        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::SendToReview);

        Event::assertDispatched(SubmissionStageChanged::class);
    }

    public function test_a_section_belongs_to_a_journal_and_denies_cross_journal_access_when_unscoped(): void
    {
        config(['journal.scope_console_bypass' => false]);

        $section = Section::create(['journal_id' => $this->journal->id, 'title' => ['en' => 'Articles']]);

        $this->assertSame(0, Section::count());
        $this->assertNotNull($section->id);
    }
}
