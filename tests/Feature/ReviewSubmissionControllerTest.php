<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\ReviewFormElementType;
use Alpha\Journal\Enums\ReviewRecommendation;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Models\ReviewForm;
use Alpha\Journal\Models\ReviewRound;
use Alpha\Journal\Models\Section;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The reviewer's own page — deliberately separate from the submission workspace (§13.2) so a
 * reviewer never sees decisions, the full people list, or other reviewers' identities. Proves
 * ownership-only authorization, the accept-before-submit gate, and that a review form's required
 * fields are actually enforced through this HTTP path (the model-level enforcement was already
 * covered by ReviewFormTest).
 */
class ReviewSubmissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $reviewer;

    private ReviewAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
        $this->reviewer = User::factory()->create();

        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => SubmissionStage::Review->value,
        ]);
        $round = ReviewRound::create(['submission_id' => $submission->id, 'round_number' => 1]);
        $this->assignment = ReviewAssignment::create(['review_round_id' => $round->id, 'reviewer_id' => $this->reviewer->id]);
    }

    public function test_a_stranger_cannot_view_another_reviewers_assignment(): void
    {
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->get(
            route('journal.reviews.show', ['journal' => $this->journal->path, 'reviewAssignment' => $this->assignment->id])
        );

        $response->assertForbidden();
    }

    public function test_the_page_prompts_to_accept_before_it_shows_the_review_form(): void
    {
        $response = $this->actingAs($this->reviewer)->get(
            route('journal.reviews.show', ['journal' => $this->journal->path, 'reviewAssignment' => $this->assignment->id])
        );

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('journal/reviews/show')
            ->where('reviewAssignment.respondedAt', null)
        );
    }

    public function test_submitting_before_accepting_is_forbidden(): void
    {
        $response = $this->actingAs($this->reviewer)->post(
            route('journal.reviews.store', ['journal' => $this->journal->path, 'reviewAssignment' => $this->assignment->id]),
            ['recommendation' => ReviewRecommendation::Accept->value]
        );

        $response->assertForbidden();
    }

    public function test_an_accepted_reviewer_can_submit_a_recommendation_with_no_review_form(): void
    {
        $this->assignment->acceptInvitation();

        $response = $this->actingAs($this->reviewer)->post(
            route('journal.reviews.store', ['journal' => $this->journal->path, 'reviewAssignment' => $this->assignment->id]),
            ['recommendation' => ReviewRecommendation::RevisionsRequired->value]
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertNotNull($this->assignment->fresh()->completed_at);
        $this->assertSame(ReviewRecommendation::RevisionsRequired, $this->assignment->fresh()->recommendation);
    }

    public function test_missing_required_review_form_answers_are_rejected(): void
    {
        $section = Section::create(['journal_id' => $this->journal->id, 'title' => ['en' => 'Articles']]);
        $form = ReviewForm::create(['journal_id' => $this->journal->id, 'title' => ['en' => 'Standard']]);
        $required = $form->elements()->create([
            'type' => ReviewFormElementType::Textarea->value,
            'label' => ['en' => 'Comments'],
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $section->update(['review_form_id' => $form->id]);
        $this->assignment->reviewRound->submission->update(['section_id' => $section->id]);
        $this->assignment->acceptInvitation();

        $response = $this->actingAs($this->reviewer)->post(
            route('journal.reviews.store', ['journal' => $this->journal->path, 'reviewAssignment' => $this->assignment->id]),
            ['recommendation' => ReviewRecommendation::Accept->value, 'answers' => []]
        );

        $response->assertSessionHasErrors('answers');
        $this->assertNull($this->assignment->fresh()->completed_at);
        $this->assertNotNull($required); // keep the created element in scope for clarity
    }

    public function test_a_review_cannot_be_submitted_twice(): void
    {
        $this->assignment->acceptInvitation();
        $this->assignment->submitReview([], ReviewRecommendation::Accept);

        $response = $this->actingAs($this->reviewer)->post(
            route('journal.reviews.store', ['journal' => $this->journal->path, 'reviewAssignment' => $this->assignment->id]),
            ['recommendation' => ReviewRecommendation::Decline->value]
        );

        $response->assertStatus(422);
    }

    public function test_a_declined_invitation_cannot_be_submitted(): void
    {
        $this->assignment->declineInvitation();

        $response = $this->actingAs($this->reviewer)->post(
            route('journal.reviews.store', ['journal' => $this->journal->path, 'reviewAssignment' => $this->assignment->id]),
            ['recommendation' => ReviewRecommendation::Accept->value]
        );

        $response->assertForbidden();
    }
}
