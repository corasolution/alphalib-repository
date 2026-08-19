<?php

namespace Tests\Feature;

use Alpha\Journal\Dashboard\JournalActionQueue;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Models\ReviewRound;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Support\JournalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalActionQueueTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private JournalActionQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
        app(JournalContext::class)->set($this->journal);

        $this->queue = app(JournalActionQueue::class);
    }

    private function submission(SubmissionStage $stage, array $overrides = []): Submission
    {
        $author = User::factory()->create();

        return Submission::create(array_merge([
            'journal_id' => $this->journal->id,
            'submitted_by' => $author->id,
            'stage' => $stage->value,
        ], $overrides));
    }

    public function test_needs_decision_counts_non_terminal_submissions_for_an_editor(): void
    {
        $editor = User::factory()->create();
        $this->journal->grantRole($editor, JournalRole::Editor);

        $this->submission(SubmissionStage::Submission);
        $this->submission(SubmissionStage::Review);
        $this->submission(SubmissionStage::Published);
        $this->submission(SubmissionStage::Declined);

        $counts = $this->queue->forUser($this->journal, $editor);

        $this->assertSame(2, $counts['needsDecision']);
    }

    public function test_needs_decision_for_section_editor_is_limited_to_assigned_submissions(): void
    {
        $sectionEditor = User::factory()->create();
        $this->journal->grantRole($sectionEditor, JournalRole::SectionEditor);

        $assigned = $this->submission(SubmissionStage::Review);
        StageAssignment::create([
            'submission_id' => $assigned->id,
            'user_id' => $sectionEditor->id,
            'stage' => SubmissionStage::Review->value,
            'role' => JournalRole::SectionEditor->value,
        ]);

        $this->submission(SubmissionStage::Review);

        $counts = $this->queue->forUser($this->journal, $sectionEditor);

        $this->assertSame(1, $counts['needsDecision']);
    }

    public function test_reviews_overdue_counts_accepted_assignments_past_due_date(): void
    {
        $submission = $this->submission(SubmissionStage::Review);
        $round = ReviewRound::create(['submission_id' => $submission->id, 'round_number' => 1]);
        $reviewer = User::factory()->create();

        ReviewAssignment::create([
            'review_round_id' => $round->id,
            'reviewer_id' => $reviewer->id,
            'due_at' => now()->subDays(2),
        ]);

        ReviewAssignment::create([
            'review_round_id' => $round->id,
            'reviewer_id' => User::factory()->create()->id,
            'due_at' => now()->addDays(2),
        ]);

        ReviewAssignment::create([
            'review_round_id' => $round->id,
            'reviewer_id' => User::factory()->create()->id,
            'due_at' => now()->subDays(2),
            'completed_at' => now(),
        ]);

        $editor = User::factory()->create();
        $counts = $this->queue->forUser($this->journal, $editor);

        $this->assertSame(1, $counts['reviewsOverdue']);
    }

    public function test_awaiting_reviewer_response_excludes_declined_and_responded(): void
    {
        $submission = $this->submission(SubmissionStage::Review);
        $round = ReviewRound::create(['submission_id' => $submission->id, 'round_number' => 1]);

        ReviewAssignment::create([
            'review_round_id' => $round->id,
            'reviewer_id' => User::factory()->create()->id,
        ]);

        ReviewAssignment::create([
            'review_round_id' => $round->id,
            'reviewer_id' => User::factory()->create()->id,
            'declined_at' => now(),
        ]);

        ReviewAssignment::create([
            'review_round_id' => $round->id,
            'reviewer_id' => User::factory()->create()->id,
            'responded_at' => now(),
        ]);

        $editor = User::factory()->create();
        $counts = $this->queue->forUser($this->journal, $editor);

        $this->assertSame(1, $counts['awaitingReviewerResponse']);
    }

    public function test_no_editor_assigned_counts_fresh_submissions_without_an_editor(): void
    {
        $unassigned = $this->submission(SubmissionStage::Submission);

        $assigned = $this->submission(SubmissionStage::Submission);
        StageAssignment::create([
            'submission_id' => $assigned->id,
            'user_id' => User::factory()->create()->id,
            'stage' => SubmissionStage::Submission->value,
            'role' => JournalRole::Editor->value,
        ]);

        $this->submission(SubmissionStage::Review);

        $editor = User::factory()->create();
        $counts = $this->queue->forUser($this->journal, $editor);

        $this->assertSame(1, $counts['noEditorAssigned']);
        $this->assertSame($unassigned->id, Submission::where('stage', SubmissionStage::Submission->value)
            ->whereDoesntHave('stageAssignments')
            ->sole()->id);
    }

    public function test_ready_to_publish_requires_both_issue_and_current_publication(): void
    {
        $issue = Issue::create(['journal_id' => $this->journal->id, 'year' => 2026]);

        $ready = $this->submission(SubmissionStage::Production, ['issue_id' => $issue->id]);
        $publication = Publication::create(['submission_id' => $ready->id, 'version' => 1, 'status' => 'draft', 'title' => ['en' => 'A Test Article']]);
        $ready->update(['current_publication_id' => $publication->id]);

        $this->submission(SubmissionStage::Production, ['issue_id' => $issue->id]);
        $this->submission(SubmissionStage::Production);

        $editor = User::factory()->create();
        $counts = $this->queue->forUser($this->journal, $editor);

        $this->assertSame(1, $counts['readyToPublish']);
    }
}
