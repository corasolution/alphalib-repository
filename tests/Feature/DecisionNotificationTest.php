<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\ActivityLog;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\JournalEmailTemplate;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Notifications\TemplatedEmailNotification;
use Alpha\Journal\Workflow\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DecisionNotificationTest extends TestCase
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

    private function makeSubmission(SubmissionStage $stage): Submission
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->author->id,
            'stage' => $stage->value,
        ]);

        StageAssignment::create([
            'submission_id' => $submission->id,
            'user_id' => $this->editor->id,
            'stage' => $stage->value,
            'role' => JournalRole::Editor->value,
        ]);

        return $submission;
    }

    public function test_accepting_a_submission_notifies_the_author_and_logs_the_mail(): void
    {
        Notification::fake();
        $submission = $this->makeSubmission(SubmissionStage::Review);

        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::Accept, 'Great work.');

        Notification::assertSentTo($this->author, TemplatedEmailNotification::class);
        $this->assertDatabaseHas('activity_log', [
            'journal_id' => $this->journal->id,
            'subject_type' => $submission->getMorphClass(),
            'subject_id' => $submission->id,
            'action' => 'mail.sent',
        ]);
    }

    public function test_a_decision_with_no_mapped_template_sends_nothing(): void
    {
        Notification::fake();
        $submission = $this->makeSubmission(SubmissionStage::Copyediting);

        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::SendToProduction);

        Notification::assertNothingSent();
    }

    public function test_disabling_the_template_on_the_journal_suppresses_the_email_without_erroring(): void
    {
        Notification::fake();
        JournalEmailTemplate::create([
            'journal_id' => $this->journal->id,
            'key' => 'submission.accepted',
            'locale' => 'en',
            'is_enabled' => false,
        ]);
        $submission = $this->makeSubmission(SubmissionStage::Review);

        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::Accept);

        Notification::assertNothingSent();
        $this->assertSame(0, ActivityLog::where('action', 'mail.sent')->count());
    }

    public function test_declining_notifies_the_author_with_the_decline_template(): void
    {
        Notification::fake();
        $submission = $this->makeSubmission(SubmissionStage::Submission);

        $this->workflow->recordDecision($submission, $this->editor, EditorialDecisionType::Decline, 'Out of scope.');

        Notification::assertSentTo(
            $this->author,
            TemplatedEmailNotification::class,
            function (TemplatedEmailNotification $notification) {
                $mail = $notification->toMail($this->author);

                return str_contains($mail->subject, 'Decision on your submission');
            }
        );
    }
}
