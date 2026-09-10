<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\ReviewType;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The one write path for editorial decisions (§13.5) — proves the route wires the HTTP layer to
 * SubmissionWorkflow correctly: legal transitions persist and redirect, illegal ones surface as
 * a validation error rather than a 500, and authorization is enforced before the workflow runs.
 */
class DecisionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $editor;

    private User $author;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
        $this->editor = User::factory()->create();
        $this->author = User::factory()->create();
        $this->journal->grantRole($this->editor, JournalRole::Editor);

        $this->submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value,
            'review_type' => ReviewType::DoubleAnonymous->value,
        ]);

        StageAssignment::create([
            'submission_id' => $this->submission->id,
            'user_id' => $this->editor->id,
            'stage' => SubmissionStage::Submission->value,
            'role' => JournalRole::Editor->value,
        ]);
    }

    public function test_an_editor_can_send_a_submission_to_review(): void
    {
        $response = $this->actingAs($this->editor)->post(
            route('journal.submissions.decisions.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['decision' => EditorialDecisionType::SendToReview->value, 'reason' => 'Looks promising.']
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame(SubmissionStage::Review, $this->submission->fresh()->stage);
        $this->assertDatabaseHas('editorial_decisions', [
            'submission_id' => $this->submission->id,
            'decision' => EditorialDecisionType::SendToReview->value,
            'reason' => 'Looks promising.',
        ]);
    }

    public function test_an_illegal_transition_is_rejected_as_a_validation_error_not_a_server_error(): void
    {
        // Submission stage has no review round yet — "accept" is not legal from Submission.
        $response = $this->actingAs($this->editor)->post(
            route('journal.submissions.decisions.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['decision' => EditorialDecisionType::Accept->value]
        );

        $response->assertSessionHasErrors('decision');
        $this->assertSame(SubmissionStage::Submission, $this->submission->fresh()->stage);
    }

    public function test_the_author_cannot_record_a_decision(): void
    {
        $response = $this->actingAs($this->author)->post(
            route('journal.submissions.decisions.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['decision' => EditorialDecisionType::SendToReview->value]
        );

        $response->assertForbidden();
        $this->assertSame(SubmissionStage::Submission, $this->submission->fresh()->stage);
    }

    public function test_a_section_editor_without_publish_authority_cannot_publish(): void
    {
        $sectionEditor = User::factory()->create();
        $this->journal->grantRole($sectionEditor, JournalRole::SectionEditor);
        StageAssignment::create([
            'submission_id' => $this->submission->id,
            'user_id' => $sectionEditor->id,
            'stage' => SubmissionStage::Submission->value,
            'role' => JournalRole::SectionEditor->value,
        ]);

        $response = $this->actingAs($sectionEditor)->post(
            route('journal.submissions.decisions.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['decision' => EditorialDecisionType::Publish->value]
        );

        $response->assertForbidden();
    }

    public function test_an_invalid_decision_value_fails_validation(): void
    {
        $response = $this->actingAs($this->editor)->post(
            route('journal.submissions.decisions.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['decision' => 'not_a_real_decision']
        );

        $response->assertSessionHasErrors('decision');
    }
}
