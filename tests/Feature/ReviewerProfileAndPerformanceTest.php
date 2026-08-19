<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Models\ReviewerProfile;
use Alpha\Journal\Models\ReviewRound;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Reviewers\ReviewerPerformanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ReviewerProfileAndPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $reviewer;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'a', 'title' => ['en' => 'Journal A']]);
        $this->reviewer = User::factory()->create();
        $this->author = User::factory()->create();
    }

    private function makeAssignment(array $overrides = []): ReviewAssignment
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Review->value,
        ]);
        $round = ReviewRound::create(['submission_id' => $submission->id, 'round_number' => 1]);

        return ReviewAssignment::create(array_merge([
            'review_round_id' => $round->id,
            'reviewer_id' => $this->reviewer->id,
        ], $overrides));
    }

    public function test_a_reviewer_profile_tracks_availability_and_interests(): void
    {
        $profile = ReviewerProfile::create([
            'journal_id' => $this->journal->id,
            'user_id' => $this->reviewer->id,
            'interests' => 'Rice genomics, soil chemistry',
            'is_available' => true,
        ]);

        $this->assertTrue($profile->isCurrentlyAvailable());
        $this->assertSame($profile->id, $this->reviewer->reviewerProfileOn($this->journal)->id);
    }

    public function test_a_reviewer_with_an_unavailable_until_date_in_the_future_is_not_available(): void
    {
        $profile = ReviewerProfile::create([
            'journal_id' => $this->journal->id,
            'user_id' => $this->reviewer->id,
            'is_available' => true,
            'unavailable_until' => now()->addWeek(),
        ]);

        $this->assertFalse($profile->isCurrentlyAvailable());
    }

    public function test_a_reviewer_becomes_available_again_once_the_unavailable_until_date_passes(): void
    {
        $profile = ReviewerProfile::create([
            'journal_id' => $this->journal->id,
            'user_id' => $this->reviewer->id,
            'is_available' => true,
            'unavailable_until' => now()->subDay(),
        ]);

        $this->assertTrue($profile->isCurrentlyAvailable());
    }

    public function test_the_manual_toggle_overrides_everything(): void
    {
        $profile = ReviewerProfile::create([
            'journal_id' => $this->journal->id,
            'user_id' => $this->reviewer->id,
            'is_available' => false,
        ]);

        $this->assertFalse($profile->isCurrentlyAvailable());
    }

    public function test_quality_rating_must_be_between_one_and_five(): void
    {
        $assignment = $this->makeAssignment(['completed_at' => now()]);

        $this->expectException(InvalidArgumentException::class);

        $assignment->rateQuality(6);
    }

    public function test_an_incomplete_review_cannot_be_rated(): void
    {
        $assignment = $this->makeAssignment();

        $this->expectException(InvalidArgumentException::class);

        $assignment->rateQuality(4);
    }

    public function test_a_valid_rating_is_stored(): void
    {
        $assignment = $this->makeAssignment(['completed_at' => now()]);

        $assignment->rateQuality(4);

        $this->assertSame(4, $assignment->fresh()->quality_rating);
    }

    public function test_performance_calculator_reports_zero_state_for_a_reviewer_with_no_history(): void
    {
        $performance = app(ReviewerPerformanceCalculator::class)->forUserOnJournal($this->reviewer, $this->journal);

        $this->assertSame(0, $performance->completedCount);
        $this->assertNull($performance->averageTurnaroundDays);
        $this->assertNull($performance->onTimeRate);
        $this->assertNull($performance->averageQualityRating);
    }

    public function test_performance_calculator_computes_turnaround_on_time_rate_and_quality(): void
    {
        // Completed on time, 2-day turnaround, rated 5.
        $a1 = $this->makeAssignment([
            'invited_at' => now()->subDays(10),
            'due_at' => now()->subDays(3),
            'completed_at' => now()->subDays(8),
        ]);
        $a1->rateQuality(5);

        // Completed late, 6-day turnaround, rated 3.
        $a2 = $this->makeAssignment([
            'invited_at' => now()->subDays(10),
            'due_at' => now()->subDays(7),
            'completed_at' => now()->subDays(4),
        ]);
        $a2->rateQuality(3);

        // Not yet completed — must not count.
        $this->makeAssignment(['invited_at' => now()->subDays(2), 'due_at' => now()->addDays(5)]);

        $performance = app(ReviewerPerformanceCalculator::class)->forUserOnJournal($this->reviewer, $this->journal);

        $this->assertSame(2, $performance->completedCount);
        $this->assertSame(4.0, $performance->averageTurnaroundDays);
        $this->assertSame(0.5, $performance->onTimeRate);
        $this->assertSame(4.0, $performance->averageQualityRating);
    }

    public function test_performance_calculator_only_counts_assignments_on_the_given_journal(): void
    {
        $otherJournal = Journal::create([
            'site_id' => $this->journal->site_id,
            'path' => 'b',
            'title' => ['en' => 'Journal B'],
        ]);
        $otherSubmission = Submission::create([
            'journal_id' => $otherJournal->id,
            'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Review->value,
        ]);
        $otherRound = ReviewRound::create(['submission_id' => $otherSubmission->id, 'round_number' => 1]);
        ReviewAssignment::create([
            'review_round_id' => $otherRound->id,
            'reviewer_id' => $this->reviewer->id,
            'completed_at' => now(),
        ]);

        $performance = app(ReviewerPerformanceCalculator::class)->forUserOnJournal($this->reviewer, $this->journal);

        $this->assertSame(0, $performance->completedCount);
    }
}
