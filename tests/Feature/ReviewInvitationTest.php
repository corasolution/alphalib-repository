<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\ReviewRound;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Notifications\TemplatedEmailNotification;
use Alpha\Journal\Reviewers\ReviewInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ReviewInvitationTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $editor;

    private User $reviewer;

    private ReviewRound $round;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Journal A']]);
        $this->editor = User::factory()->create();
        $this->reviewer = User::factory()->create();
        $author = User::factory()->create();
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $author->id,
            'stage' => SubmissionStage::Review->value,
        ]);
        $this->round = ReviewRound::create(['submission_id' => $submission->id, 'round_number' => 1]);
    }

    public function test_inviting_a_reviewer_creates_an_assignment_and_emails_working_signed_links(): void
    {
        Notification::fake();

        $assignment = app(ReviewInvitationService::class)->invite(
            $this->round, $this->reviewer, $this->editor, now()->addDays(21)
        );

        $this->assertNotNull($assignment->invited_at);
        $this->assertNotNull($assignment->due_at);

        Notification::assertSentTo($this->reviewer, TemplatedEmailNotification::class, function (TemplatedEmailNotification $notification) use ($assignment) {
            $mail = $notification->toMail($this->reviewer);
            $body = implode("\n", $mail->introLines);

            $this->assertStringNotContainsString('{{', $body, 'unresolved template placeholder leaked into the email');
            $this->assertMatchesRegularExpression('#/journal/demo/reviews/'.$assignment->id.'/accept\?[^\s]*signature=#', $body);
            $this->assertMatchesRegularExpression('#/journal/demo/reviews/'.$assignment->id.'/decline\?[^\s]*signature=#', $body);

            return true;
        });
    }

    public function test_a_reviewer_can_accept_without_logging_in(): void
    {
        $assignment = app(ReviewInvitationService::class)->invite($this->round, $this->reviewer, $this->editor);
        $url = URL::temporarySignedRoute(
            'journal.reviews.accept',
            now()->addDays(14),
            ['journal' => $this->journal->path, 'reviewAssignment' => $assignment->id]
        );

        $response = $this->get($url);

        $response->assertOk()->assertJson(['status' => 'accepted']);
        $this->assertNotNull($assignment->fresh()->responded_at);
        $this->assertNull($assignment->fresh()->declined_at);
        $this->assertGuest();
    }

    public function test_a_reviewer_can_decline_without_logging_in(): void
    {
        $assignment = app(ReviewInvitationService::class)->invite($this->round, $this->reviewer, $this->editor);
        $url = URL::temporarySignedRoute(
            'journal.reviews.decline',
            now()->addDays(14),
            ['journal' => $this->journal->path, 'reviewAssignment' => $assignment->id]
        );

        $response = $this->get($url);

        $response->assertOk()->assertJson(['status' => 'declined']);
        $this->assertNotNull($assignment->fresh()->responded_at);
        $this->assertNotNull($assignment->fresh()->declined_at);
    }

    public function test_a_tampered_link_is_rejected(): void
    {
        $assignment = app(ReviewInvitationService::class)->invite($this->round, $this->reviewer, $this->editor);
        $url = URL::temporarySignedRoute(
            'journal.reviews.accept',
            now()->addDays(14),
            ['journal' => $this->journal->path, 'reviewAssignment' => $assignment->id]
        );

        $response = $this->get($url.'&tampered=1');

        $response->assertForbidden();
        $this->assertNull($assignment->fresh()->responded_at);
    }

    public function test_an_expired_link_is_rejected(): void
    {
        $assignment = app(ReviewInvitationService::class)->invite($this->round, $this->reviewer, $this->editor);
        $url = URL::temporarySignedRoute(
            'journal.reviews.accept',
            now()->subDay(),
            ['journal' => $this->journal->path, 'reviewAssignment' => $assignment->id]
        );

        $response = $this->get($url);

        $response->assertForbidden();
    }

    public function test_a_review_cannot_be_accepted_twice(): void
    {
        $assignment = app(ReviewInvitationService::class)->invite($this->round, $this->reviewer, $this->editor);
        $assignment->acceptInvitation();
        $url = URL::temporarySignedRoute(
            'journal.reviews.accept',
            now()->addDays(14),
            ['journal' => $this->journal->path, 'reviewAssignment' => $assignment->id]
        );

        $response = $this->get($url);

        $response->assertStatus(422);
    }

    public function test_a_completed_review_cannot_be_declined(): void
    {
        $assignment = app(ReviewInvitationService::class)->invite($this->round, $this->reviewer, $this->editor);
        $assignment->update(['completed_at' => now()]);
        $url = URL::temporarySignedRoute(
            'journal.reviews.decline',
            now()->addDays(14),
            ['journal' => $this->journal->path, 'reviewAssignment' => $assignment->id]
        );

        $response = $this->get($url);

        $response->assertStatus(422);
    }
}
