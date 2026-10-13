<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\JournalSetting;
use Alpha\Journal\Models\PlagiarismCheck;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Workflow\Exceptions\WorkflowTransitionException;
use Alpha\Journal\Workflow\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Similarity screening.
 *
 * The record on its own is just a note; what makes a screening policy real is the gate. The tests
 * that matter are the ones proving an unscreened — or badly screened — manuscript cannot reach
 * reviewers in a journal that says it screens, because "we ran it" and "it came back clean" are
 * different claims and only one of them should open the door.
 */
class PlagiarismCheckTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');

        $site = Site::create(['title' => 'BIU Journal']);
        $this->journal = Journal::create([
            'site_id' => $site->id, 'path' => 'biu', 'title' => ['en' => 'BIU Journal of Research'],
            'primary_locale' => 'en', 'is_enabled' => true,
        ]);

        $this->editor = User::factory()->create();
        $this->editor->journalRoles()->create(['journal_id' => $this->journal->id, 'role' => JournalRole::Editor->value]);
    }

    private function submission(): Submission
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => SubmissionStage::Submission->value,
            'submitted_at' => now(),
        ]);

        $submission->stageAssignments()->create([
            'user_id' => $this->editor->id,
            'stage' => SubmissionStage::Submission->value,
            'role' => JournalRole::Editor->value,
        ]);

        return $submission;
    }

    private function requireScreening(): void
    {
        JournalSetting::updateOrCreate(
            ['journal_id' => $this->journal->id, 'name' => 'require_plagiarism_check', 'locale' => ''],
            ['value' => '1'],
        );
    }

    private function url(Submission $submission): string
    {
        return "/journal/{$this->journal->path}/submissions/{$submission->id}/plagiarism-checks";
    }

    // ---------- Recording ----------

    public function test_an_editor_can_record_a_check(): void
    {
        $submission = $this->submission();

        $this->actingAs($this->editor)->post($this->url($submission), [
            'status' => PlagiarismCheck::STATUS_PASSED,
            'similarity_percent' => 8,
            'tool' => 'iThenticate',
            'notes' => 'Matches are the reference list only.',
        ])->assertRedirect();

        $check = $submission->latestPlagiarismCheck();

        $this->assertSame(PlagiarismCheck::STATUS_PASSED, $check->status);
        $this->assertSame(8, $check->similarity_percent);
        $this->assertSame('iThenticate', $check->tool);
        // Attribution is the point of a record — an unattributed "it was checked" is worth nothing.
        $this->assertSame($this->editor->id, $check->checked_by);
        $this->assertNotNull($check->checked_at);
    }

    public function test_a_report_can_be_attached_and_fetched(): void
    {
        $submission = $this->submission();

        $this->actingAs($this->editor)->post($this->url($submission), [
            'status' => PlagiarismCheck::STATUS_FLAGGED,
            'similarity_percent' => 42,
            'report' => UploadedFile::fake()->create('similarity.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $check = $submission->latestPlagiarismCheck();

        $this->assertTrue($check->hasReport());
        Storage::disk($check->disk)->assertExists($check->path);

        $this->actingAs($this->editor)
            ->get($this->url($submission)."/{$check->id}/report")
            ->assertRedirect();
    }

    public function test_one_submissions_report_is_not_reachable_through_another(): void
    {
        $mine = $this->submission();
        $theirs = $this->submission();

        $this->actingAs($this->editor)->post($this->url($theirs), [
            'status' => PlagiarismCheck::STATUS_PASSED,
            'report' => UploadedFile::fake()->create('theirs.pdf', 10, 'application/pdf'),
        ]);

        $check = $theirs->latestPlagiarismCheck();

        $this->actingAs($this->editor)
            ->get($this->url($mine)."/{$check->id}/report")
            ->assertNotFound();
    }

    public function test_a_reviewer_cannot_record_a_check(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->journalRoles()->create(['journal_id' => $this->journal->id, 'role' => JournalRole::Reviewer->value]);

        $this->actingAs($reviewer)
            ->post($this->url($this->submission()), ['status' => PlagiarismCheck::STATUS_PASSED])
            ->assertForbidden();
    }

    public function test_an_impossible_similarity_is_refused(): void
    {
        $this->actingAs($this->editor)
            ->post($this->url($this->submission()), ['status' => PlagiarismCheck::STATUS_PASSED, 'similarity_percent' => 140])
            ->assertSessionHasErrors('similarity_percent');
    }

    // ---------- The gate ----------

    public function test_without_the_requirement_review_is_reachable_unscreened(): void
    {
        // The regression guard for every journal already running.
        $submission = $this->submission();

        app(SubmissionWorkflow::class)->recordDecision($submission, $this->editor, EditorialDecisionType::SendToReview);

        $this->assertSame(SubmissionStage::Review, $submission->refresh()->stage);
    }

    public function test_an_unscreened_manuscript_cannot_reach_reviewers(): void
    {
        $this->requireScreening();
        $submission = $this->submission();

        $this->expectException(WorkflowTransitionException::class);

        app(SubmissionWorkflow::class)->recordDecision($submission, $this->editor, EditorialDecisionType::SendToReview);
    }

    public function test_a_flagged_result_does_not_open_the_door(): void
    {
        $this->requireScreening();
        $submission = $this->submission();
        PlagiarismCheck::record($submission, $this->editor, PlagiarismCheck::STATUS_FLAGGED, 61);

        $this->expectException(WorkflowTransitionException::class);

        app(SubmissionWorkflow::class)->recordDecision($submission, $this->editor, EditorialDecisionType::SendToReview);
    }

    public function test_a_pending_result_does_not_open_the_door_either(): void
    {
        // "Sent to the tool" is not "came back clean".
        $this->requireScreening();
        $submission = $this->submission();
        PlagiarismCheck::record($submission, $this->editor, PlagiarismCheck::STATUS_PENDING);

        $this->expectException(WorkflowTransitionException::class);

        app(SubmissionWorkflow::class)->recordDecision($submission, $this->editor, EditorialDecisionType::SendToReview);
    }

    public function test_a_passed_result_lets_it_through(): void
    {
        $this->requireScreening();
        $submission = $this->submission();
        PlagiarismCheck::record($submission, $this->editor, PlagiarismCheck::STATUS_PASSED, 7);

        app(SubmissionWorkflow::class)->recordDecision($submission, $this->editor, EditorialDecisionType::SendToReview);

        $this->assertSame(SubmissionStage::Review, $submission->refresh()->stage);
    }

    public function test_the_latest_result_is_the_one_that_counts(): void
    {
        // A re-check after corrections must be able to clear an earlier flag.
        $this->requireScreening();
        $submission = $this->submission();

        PlagiarismCheck::record($submission, $this->editor, PlagiarismCheck::STATUS_FLAGGED, 55);
        $this->travel(1)->minutes();
        PlagiarismCheck::record($submission, $this->editor, PlagiarismCheck::STATUS_PASSED, 9);

        app(SubmissionWorkflow::class)->recordDecision($submission->refresh(), $this->editor, EditorialDecisionType::SendToReview);

        $this->assertSame(SubmissionStage::Review, $submission->refresh()->stage);
    }

    public function test_the_gate_does_not_block_declining_or_returning(): void
    {
        // Screening gates peer review, not the editor's ability to deal with the manuscript —
        // a flagged paper must still be declinable without first being made to pass.
        $this->requireScreening();
        $submission = $this->submission();
        PlagiarismCheck::record($submission, $this->editor, PlagiarismCheck::STATUS_FLAGGED, 80);

        app(SubmissionWorkflow::class)->recordDecision($submission, $this->editor, EditorialDecisionType::ReturnToAuthor);
        $this->assertNotNull($submission->refresh()->returned_to_author_at);

        app(SubmissionWorkflow::class)->recordDecision($submission, $this->editor, EditorialDecisionType::Decline);
        $this->assertSame(SubmissionStage::Declined, $submission->refresh()->stage);
    }
}
