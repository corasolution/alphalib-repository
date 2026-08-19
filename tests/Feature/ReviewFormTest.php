<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\ReviewFormElementType;
use Alpha\Journal\Enums\ReviewRecommendation;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Exceptions\ReviewFormValidationException;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Models\ReviewForm;
use Alpha\Journal\Models\ReviewFormElement;
use Alpha\Journal\Models\ReviewRound;
use Alpha\Journal\Models\Section;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewFormTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $reviewer;

    private ReviewAssignment $assignment;

    private ReviewForm $form;

    private ReviewFormElement $requiredTextElement;

    private ReviewFormElement $optionalElement;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'a', 'title' => ['en' => 'Journal A']]);
        $this->form = ReviewForm::create(['journal_id' => $this->journal->id, 'title' => ['en' => 'Standard Review']]);
        $this->requiredTextElement = $this->form->elements()->create([
            'type' => ReviewFormElementType::Textarea->value,
            'label' => ['en' => 'Comments for the editor'],
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $this->optionalElement = $this->form->elements()->create([
            'type' => ReviewFormElementType::Rating->value,
            'label' => ['en' => 'Overall quality'],
            'is_required' => false,
            'options' => ['1', '2', '3', '4', '5'],
            'sort_order' => 2,
        ]);

        $section = Section::create([
            'journal_id' => $this->journal->id,
            'title' => ['en' => 'Articles'],
            'review_form_id' => $this->form->id,
        ]);
        $author = User::factory()->create();
        $this->reviewer = User::factory()->create();
        $this->journal->grantRole($this->reviewer, JournalRole::Reviewer);

        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'section_id' => $section->id,
            'submitted_by' => $author->id,
            'stage' => SubmissionStage::Review->value,
        ]);
        $round = ReviewRound::create(['submission_id' => $submission->id, 'round_number' => 1]);
        $this->assignment = ReviewAssignment::create([
            'review_round_id' => $round->id,
            'reviewer_id' => $this->reviewer->id,
        ]);
    }

    public function test_missing_required_elements_are_detected(): void
    {
        $missing = $this->form->missingRequiredElements([$this->optionalElement->id => '4']);

        $this->assertCount(1, $missing);
        $this->assertSame($this->requiredTextElement->id, $missing->first()->id);
    }

    public function test_submitting_a_review_without_a_required_answer_throws(): void
    {
        $this->expectException(ReviewFormValidationException::class);

        $this->assignment->submitReview(
            [$this->optionalElement->id => '4'],
            ReviewRecommendation::Accept
        );
    }

    public function test_submitting_a_complete_review_stores_responses_and_completes_the_assignment(): void
    {
        $this->assignment->submitReview(
            [
                $this->requiredTextElement->id => 'Solid methodology, minor typos.',
                $this->optionalElement->id => '4',
            ],
            ReviewRecommendation::RevisionsRequired
        );

        $fresh = $this->assignment->fresh();
        $this->assertSame(ReviewRecommendation::RevisionsRequired, $fresh->recommendation);
        $this->assertNotNull($fresh->completed_at);
        $this->assertSame(2, $fresh->responses()->count());

        $textResponse = $fresh->responses()->where('review_form_element_id', $this->requiredTextElement->id)->first();
        $this->assertSame('Solid methodology, minor typos.', $textResponse->value);
    }

    public function test_resubmitting_updates_existing_responses_rather_than_duplicating(): void
    {
        $this->assignment->submitReview(
            [$this->requiredTextElement->id => 'First draft comment.'],
            ReviewRecommendation::RevisionsRequired
        );
        $this->assignment->submitReview(
            [$this->requiredTextElement->id => 'Updated comment.'],
            ReviewRecommendation::Accept
        );

        $this->assertSame(1, $this->assignment->responses()->count());
        $this->assertSame('Updated comment.', $this->assignment->responses()->first()->value);
    }

    public function test_a_submission_with_no_review_form_configured_skips_validation_entirely(): void
    {
        $noFormAssignment = ReviewAssignment::create([
            'review_round_id' => ReviewRound::create([
                'submission_id' => Submission::create([
                    'journal_id' => $this->journal->id,
                    'submitted_by' => User::factory()->create()->id,
                    'stage' => SubmissionStage::Review->value,
                ])->id,
                'round_number' => 1,
            ])->id,
            'reviewer_id' => $this->reviewer->id,
        ]);

        $noFormAssignment->submitReview([], ReviewRecommendation::Accept);

        $this->assertSame(ReviewRecommendation::Accept, $noFormAssignment->fresh()->recommendation);
    }
}
