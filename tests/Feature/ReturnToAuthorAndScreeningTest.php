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
use Alpha\Journal\Workflow\Exceptions\WorkflowTransitionException;
use Alpha\Journal\Workflow\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Returning a manuscript to its author, and the optional screening stage.
 *
 * Both exist because a real published workflow distinguishes "this is not ready for review" from
 * "this is rejected", and OJS does not — at the submission stage its only outcomes are send to
 * review, accept, or decline. The tests that matter are the ones proving the distinction holds:
 * that returning does not advance, does not reject, and does not quietly leave the submission in
 * the editor's queue.
 */
class ReturnToAuthorAndScreeningTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $editor;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'BIU Journal']);
        $this->journal = Journal::create([
            'site_id' => $site->id, 'path' => 'biu', 'title' => ['en' => 'BIU Journal of Research'],
            'primary_locale' => 'en', 'is_enabled' => true,
        ]);

        $this->editor = User::factory()->create();
        $this->editor->journalRoles()->create(['journal_id' => $this->journal->id, 'role' => JournalRole::Editor->value]);

        $this->author = User::factory()->create();
        $this->author->journalRoles()->create(['journal_id' => $this->journal->id, 'role' => JournalRole::Author->value]);
    }

    private function submission(string $stage = 'submission'): Submission
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->author->id,
            'stage' => $stage,
            'submitted_at' => now(),
        ]);

        // Every decision requires an editor on the current stage.
        $submission->stageAssignments()->create([
            'user_id' => $this->editor->id,
            'stage' => $stage,
            'role' => JournalRole::Editor->value,
        ]);

        return $submission;
    }

    private function screensOn(): void
    {
        JournalSetting::updateOrCreate(
            ['journal_id' => $this->journal->id, 'name' => 'use_screening_stage', 'locale' => ''],
            ['value' => '1'],
        );
    }

    // ---------- Return to author ----------

    public function test_returning_keeps_the_stage_and_marks_it_as_with_the_author(): void
    {
        $submission = $this->submission();

        app(SubmissionWorkflow::class)->recordDecision(
            $submission, $this->editor, EditorialDecisionType::ReturnToAuthor, 'Please use the journal template.'
        );

        $submission->refresh();

        // Not advanced, not declined — the whole point.
        $this->assertSame(SubmissionStage::Submission, $submission->stage);
        $this->assertNotNull($submission->returned_to_author_at);
    }

    public function test_it_can_be_returned_from_review_too(): void
    {
        $submission = $this->submission('review');

        app(SubmissionWorkflow::class)->recordDecision(
            $submission, $this->editor, EditorialDecisionType::ReturnToAuthor, 'Reviewers could not assess the methods.'
        );

        $this->assertSame(SubmissionStage::Review, $submission->refresh()->stage);
        $this->assertNotNull($submission->returned_to_author_at);
    }

    public function test_a_returned_submission_cannot_be_returned_from_a_terminal_stage(): void
    {
        $submission = $this->submission('published');

        $this->expectException(WorkflowTransitionException::class);

        app(SubmissionWorkflow::class)->recordDecision(
            $submission, $this->editor, EditorialDecisionType::ReturnToAuthor
        );
    }

    public function test_a_new_manuscript_takes_it_back_off_the_author(): void
    {
        $submission = $this->submission();
        app(SubmissionWorkflow::class)->recordDecision($submission, $this->editor, EditorialDecisionType::ReturnToAuthor);
        $this->assertNotNull($submission->refresh()->returned_to_author_at);

        $this->actingAs($this->author)->post(
            "/journal/{$this->journal->path}/submissions/{$submission->id}/files",
            ['file' => \Illuminate\Http\UploadedFile::fake()->create('revised.pdf', 80), 'as_manuscript' => true],
        );

        $this->assertNull($submission->refresh()->returned_to_author_at);
    }

    public function test_a_supporting_file_does_not_take_it_back(): void
    {
        // Adding a dataset is not an answer to "fix your formatting"; the editor's list must keep
        // showing it as outstanding.
        $submission = $this->submission();
        app(SubmissionWorkflow::class)->recordDecision($submission, $this->editor, EditorialDecisionType::ReturnToAuthor);

        $this->actingAs($this->author)->post(
            "/journal/{$this->journal->path}/submissions/{$submission->id}/files",
            ['file' => \Illuminate\Http\UploadedFile::fake()->create('data.pdf', 20), 'as_manuscript' => false],
        );

        $this->assertNotNull($submission->refresh()->returned_to_author_at);
    }

    public function test_any_later_decision_clears_the_with_author_flag(): void
    {
        $submission = $this->submission();
        app(SubmissionWorkflow::class)->recordDecision($submission, $this->editor, EditorialDecisionType::ReturnToAuthor);

        app(SubmissionWorkflow::class)->recordDecision($submission->refresh(), $this->editor, EditorialDecisionType::Decline);

        $submission->refresh();
        $this->assertSame(SubmissionStage::Declined, $submission->stage);
        $this->assertNull($submission->returned_to_author_at);
    }

    // ---------- Screening ----------

    public function test_screening_is_not_offered_unless_the_journal_turns_it_on(): void
    {
        $decisions = app(SubmissionWorkflow::class)->legalDecisionsFrom($this->submission());

        $this->assertNotContains(EditorialDecisionType::SendToScreening, $decisions);
        $this->assertContains(EditorialDecisionType::SendToReview, $decisions);
    }

    public function test_with_screening_on_review_is_reached_through_it_not_around_it(): void
    {
        $this->screensOn();

        $decisions = app(SubmissionWorkflow::class)->legalDecisionsFrom($this->submission());

        $this->assertContains(EditorialDecisionType::SendToScreening, $decisions);
        $this->assertNotContains(EditorialDecisionType::SendToReview, $decisions);
    }

    public function test_screening_cannot_be_skipped_even_by_posting_directly(): void
    {
        // The UI hiding an option is not enforcement (§3).
        $this->screensOn();
        $submission = $this->submission();

        $this->expectException(WorkflowTransitionException::class);

        app(SubmissionWorkflow::class)->recordDecision($submission, $this->editor, EditorialDecisionType::SendToReview);
    }

    public function test_the_full_screened_path_reaches_review(): void
    {
        $this->screensOn();
        $submission = $this->submission();
        $workflow = app(SubmissionWorkflow::class);

        $workflow->recordDecision($submission, $this->editor, EditorialDecisionType::SendToScreening);
        $this->assertSame(SubmissionStage::Screening, $submission->refresh()->stage);

        $workflow->recordDecision($submission, $this->editor, EditorialDecisionType::SendToReview);
        $this->assertSame(SubmissionStage::Review, $submission->refresh()->stage);
    }

    public function test_a_submission_can_be_returned_or_declined_from_screening(): void
    {
        $this->screensOn();
        $submission = $this->submission('screening');

        $decisions = app(SubmissionWorkflow::class)->legalDecisionsFrom($submission);

        $this->assertContains(EditorialDecisionType::ReturnToAuthor, $decisions);
        $this->assertContains(EditorialDecisionType::Decline, $decisions);
        $this->assertContains(EditorialDecisionType::SendToReview, $decisions);
    }

    public function test_journals_that_do_not_screen_are_completely_unaffected(): void
    {
        // The existing path must behave exactly as before — this is the regression guard for
        // every journal already running.
        $submission = $this->submission();
        $workflow = app(SubmissionWorkflow::class);

        $workflow->recordDecision($submission, $this->editor, EditorialDecisionType::SendToReview);

        $this->assertSame(SubmissionStage::Review, $submission->refresh()->stage);
    }
}
