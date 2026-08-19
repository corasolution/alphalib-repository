<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\ActivityLog;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Models\ReviewRound;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Notifications\TemplatedEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ScheduledTasksTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $author;

    private User $reviewer;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'a', 'title' => ['en' => 'Journal A']]);
        $this->author = User::factory()->create();
        $this->reviewer = User::factory()->create();
        $this->submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Review->value,
            'submitted_at' => now(),
        ]);
    }

    private function makeAssignment(\DateTimeInterface $dueAt): ReviewAssignment
    {
        $round = ReviewRound::create(['submission_id' => $this->submission->id, 'round_number' => 1]);

        return ReviewAssignment::create([
            'review_round_id' => $round->id,
            'reviewer_id' => $this->reviewer->id,
            'due_at' => $dueAt,
        ]);
    }

    public function test_it_reminds_a_reviewer_whose_due_date_is_approaching(): void
    {
        Notification::fake();
        $assignment = $this->makeAssignment(now()->addDays(2));

        $this->artisan('journal:send-reviewer-reminders')->assertSuccessful();

        Notification::assertSentTo($this->reviewer, TemplatedEmailNotification::class);
        $this->assertNotNull($assignment->fresh()->last_reminder_sent_at);
    }

    public function test_it_does_not_remind_twice_for_the_same_upcoming_window(): void
    {
        Notification::fake();
        $this->makeAssignment(now()->addDays(2));

        $this->artisan('journal:send-reviewer-reminders');
        $this->artisan('journal:send-reviewer-reminders');

        Notification::assertSentToTimes($this->reviewer, TemplatedEmailNotification::class, 1);
    }

    public function test_it_does_not_remind_a_reviewer_whose_due_date_is_far_away(): void
    {
        Notification::fake();
        $this->makeAssignment(now()->addDays(30));

        $this->artisan('journal:send-reviewer-reminders');

        Notification::assertNothingSent();
    }

    public function test_it_reminds_an_overdue_reviewer(): void
    {
        Notification::fake();
        $assignment = $this->makeAssignment(now()->subDays(1));

        $this->artisan('journal:send-reviewer-reminders');

        Notification::assertSentTo($this->reviewer, TemplatedEmailNotification::class);
        $this->assertNotNull($assignment->fresh()->last_reminder_sent_at);
    }

    public function test_it_does_not_re_send_an_overdue_reminder_before_the_escalation_interval_elapses(): void
    {
        Notification::fake();
        $assignment = $this->makeAssignment(now()->subDays(1));
        $assignment->update(['last_reminder_sent_at' => now()->subDay()]);

        $this->artisan('journal:send-reviewer-reminders');

        Notification::assertNothingSent();
    }

    public function test_a_completed_review_is_never_reminded(): void
    {
        Notification::fake();
        $assignment = $this->makeAssignment(now()->subDays(1));
        $assignment->update(['completed_at' => now()]);

        $this->artisan('journal:send-reviewer-reminders');

        Notification::assertNothingSent();
    }

    public function test_it_escalates_a_significantly_overdue_review_to_the_assigned_editor(): void
    {
        Notification::fake();
        $editor = User::factory()->create();
        $this->journal->grantRole($editor, JournalRole::Editor);
        StageAssignment::create([
            'submission_id' => $this->submission->id,
            'user_id' => $editor->id,
            'stage' => SubmissionStage::Review->value,
            'role' => JournalRole::Editor->value,
        ]);
        $assignment = $this->makeAssignment(now()->subDays(10));

        $this->artisan('journal:escalate-overdue-reviews')->assertSuccessful();

        Notification::assertSentTo($editor, TemplatedEmailNotification::class);
        Notification::assertNotSentTo($this->reviewer, TemplatedEmailNotification::class);
        $this->assertNotNull($assignment->fresh()->editor_escalated_at);
        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $this->submission->id,
            'action' => 'review.overdue_escalated',
        ]);
    }

    public function test_escalation_does_not_fire_before_the_threshold(): void
    {
        Notification::fake();
        $editor = User::factory()->create();
        $this->journal->grantRole($editor, JournalRole::Editor);
        $this->makeAssignment(now()->subDays(2));

        $this->artisan('journal:escalate-overdue-reviews');

        Notification::assertNothingSent();
    }

    public function test_escalation_only_fires_once(): void
    {
        Notification::fake();
        $editor = User::factory()->create();
        $this->journal->grantRole($editor, JournalRole::Editor);
        StageAssignment::create([
            'submission_id' => $this->submission->id,
            'user_id' => $editor->id,
            'stage' => SubmissionStage::Review->value,
            'role' => JournalRole::Editor->value,
        ]);
        $this->makeAssignment(now()->subDays(10));

        $this->artisan('journal:escalate-overdue-reviews');
        $this->artisan('journal:escalate-overdue-reviews');

        Notification::assertSentToTimes($editor, TemplatedEmailNotification::class, 1);
    }

    public function test_it_alerts_editors_about_an_unclaimed_submission(): void
    {
        Notification::fake();
        $editor = User::factory()->create();
        $this->journal->grantRole($editor, JournalRole::Editor);
        $unclaimed = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value,
            'submitted_at' => now()->subDays(10),
        ]);

        $this->artisan('journal:alert-unclaimed-submissions')->assertSuccessful();

        Notification::assertSentTo($editor, TemplatedEmailNotification::class);
        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $unclaimed->id,
            'action' => 'submission.unclaimed_alert',
        ]);
    }

    public function test_a_submission_with_an_assigned_editor_is_never_flagged_as_unclaimed(): void
    {
        Notification::fake();
        $editor = User::factory()->create();
        $this->journal->grantRole($editor, JournalRole::Editor);
        $claimed = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value,
            'submitted_at' => now()->subDays(10),
        ]);
        StageAssignment::create([
            'submission_id' => $claimed->id,
            'user_id' => $editor->id,
            'stage' => SubmissionStage::Submission->value,
            'role' => JournalRole::Editor->value,
        ]);

        $this->artisan('journal:alert-unclaimed-submissions');

        Notification::assertNothingSent();
    }

    public function test_unclaimed_alert_does_not_fire_twice_in_the_same_day(): void
    {
        Notification::fake();
        $editor = User::factory()->create();
        $this->journal->grantRole($editor, JournalRole::Editor);
        Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value,
            'submitted_at' => now()->subDays(10),
        ]);

        $this->artisan('journal:alert-unclaimed-submissions');
        $this->artisan('journal:alert-unclaimed-submissions');

        Notification::assertSentToTimes($editor, TemplatedEmailNotification::class, 1);
        $this->assertSame(1, ActivityLog::where('action', 'submission.unclaimed_alert')->count());
    }
}
