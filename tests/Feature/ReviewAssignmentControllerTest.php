<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\ReviewRound;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Notifications\TemplatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The editor-side HTTP entry point onto ReviewInvitationService (which already had full unit
 * coverage in ReviewInvitationTest) — proves the route/controller actually wires an editor
 * clicking "Invite reviewer" through to a real ReviewAssignment + email, and that a round is
 * created automatically on the first invitation rather than requiring a separate step.
 */
class ReviewAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $editor;

    private User $reviewer;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
        $this->editor = User::factory()->create();
        $this->reviewer = User::factory()->create();
        $this->journal->grantRole($this->editor, JournalRole::Editor);
        $this->journal->grantRole($this->reviewer, JournalRole::Reviewer);

        $this->submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => SubmissionStage::Review->value,
        ]);
    }

    public function test_an_editor_can_invite_a_reviewer_and_a_review_round_is_created(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->editor)->post(
            route('journal.submissions.review-assignments.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['reviewer_id' => $this->reviewer->id, 'due_at' => now()->addDays(21)->toDateString()]
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $round = ReviewRound::where('submission_id', $this->submission->id)->sole();
        $this->assertSame(1, $round->round_number);

        $assignment = $round->reviewAssignments()->sole();
        $this->assertSame($this->reviewer->id, $assignment->reviewer_id);
        $this->assertNotNull($assignment->invited_at);

        Notification::assertSentTo($this->reviewer, TemplatedNotification::class);
    }

    public function test_inviting_a_second_reviewer_reuses_the_open_round(): void
    {
        Notification::fake();
        $secondReviewer = User::factory()->create();
        $this->journal->grantRole($secondReviewer, JournalRole::Reviewer);

        $this->actingAs($this->editor)->post(
            route('journal.submissions.review-assignments.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['reviewer_id' => $this->reviewer->id]
        );
        $this->actingAs($this->editor)->post(
            route('journal.submissions.review-assignments.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['reviewer_id' => $secondReviewer->id]
        );

        $this->assertSame(1, ReviewRound::where('submission_id', $this->submission->id)->count());
    }

    public function test_a_user_without_the_reviewer_role_cannot_be_invited(): void
    {
        $notAReviewer = User::factory()->create();

        $response = $this->actingAs($this->editor)->post(
            route('journal.submissions.review-assignments.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['reviewer_id' => $notAReviewer->id]
        );

        $response->assertSessionHasErrors('reviewer_id');
    }

    public function test_an_author_cannot_invite_a_reviewer(): void
    {
        $author = User::factory()->create();
        $this->submission->update(['submitted_by' => $author->id]);

        $response = $this->actingAs($author)->post(
            route('journal.submissions.review-assignments.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['reviewer_id' => $this->reviewer->id]
        );

        $response->assertForbidden();
    }

    public function test_a_section_editor_not_assigned_to_the_submission_cannot_invite_a_reviewer(): void
    {
        $sectionEditor = User::factory()->create();
        $this->journal->grantRole($sectionEditor, JournalRole::SectionEditor);

        $response = $this->actingAs($sectionEditor)->post(
            route('journal.submissions.review-assignments.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['reviewer_id' => $this->reviewer->id]
        );

        $response->assertForbidden();
    }

    public function test_an_assigned_section_editor_can_invite_a_reviewer(): void
    {
        Notification::fake();
        $sectionEditor = User::factory()->create();
        $this->journal->grantRole($sectionEditor, JournalRole::SectionEditor);
        StageAssignment::create([
            'submission_id' => $this->submission->id,
            'user_id' => $sectionEditor->id,
            'stage' => SubmissionStage::Review->value,
            'role' => JournalRole::SectionEditor->value,
        ]);

        $response = $this->actingAs($sectionEditor)->post(
            route('journal.submissions.review-assignments.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['reviewer_id' => $this->reviewer->id]
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }
}
