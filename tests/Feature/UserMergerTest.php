<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\ActivityLog;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\IssueEditorAssignment;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Models\ReviewerProfile;
use Alpha\Journal\Models\ReviewRound;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Users\UserMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class UserMergerTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $keep;

    private User $duplicate;

    private UserMerger $merger;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'a', 'title' => ['en' => 'Journal A']]);
        $this->keep = User::factory()->create(['email' => 'keep@example.com']);
        $this->duplicate = User::factory()->create(['email' => 'duplicate@example.com']);
        $this->merger = app(UserMerger::class);
    }

    public function test_cannot_merge_a_user_with_themselves(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->merger->merge($this->keep, $this->keep);
    }

    public function test_the_duplicate_account_is_deleted_after_merge(): void
    {
        $this->merger->merge($this->keep, $this->duplicate);

        $this->assertDatabaseMissing('users', ['id' => $this->duplicate->id]);
        $this->assertDatabaseHas('users', ['id' => $this->keep->id]);
    }

    public function test_submission_authorship_is_reassigned(): void
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->duplicate->id,
            'stage' => SubmissionStage::Submission->value,
        ]);

        $this->merger->merge($this->keep, $this->duplicate);

        $this->assertSame($this->keep->id, $submission->fresh()->submitted_by);
    }

    public function test_reviewer_identity_on_completed_reviews_is_reassigned(): void
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->keep->id,
            'stage' => SubmissionStage::Review->value,
        ]);
        $round = ReviewRound::create(['submission_id' => $submission->id, 'round_number' => 1]);
        $assignment = ReviewAssignment::create(['review_round_id' => $round->id, 'reviewer_id' => $this->duplicate->id]);

        $this->merger->merge($this->keep, $this->duplicate);

        $this->assertSame($this->keep->id, $assignment->fresh()->reviewer_id);
    }

    public function test_editorial_decision_actor_is_reassigned_despite_the_model_being_immutable(): void
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->keep->id,
            'stage' => SubmissionStage::Submission->value,
        ]);
        StageAssignment::create([
            'submission_id' => $submission->id,
            'user_id' => $this->duplicate->id,
            'stage' => SubmissionStage::Submission->value,
            'role' => JournalRole::Editor->value,
        ]);
        $this->journal->grantRole($this->duplicate, JournalRole::Editor);
        $decision = $submission->editorialDecisions()->create([
            'actor_id' => $this->duplicate->id,
            'role_used' => JournalRole::Editor->value,
            'decision' => EditorialDecisionType::SendToReview->value,
            'target_stage' => SubmissionStage::Review->value,
        ]);

        $this->merger->merge($this->keep, $this->duplicate);

        $this->assertSame($this->keep->id, $decision->fresh()->actor_id);
    }

    public function test_activity_log_authorship_is_reassigned(): void
    {
        ActivityLog::create([
            'journal_id' => $this->journal->id,
            'user_id' => $this->duplicate->id,
            'action' => 'test.action',
        ]);

        $this->merger->merge($this->keep, $this->duplicate);

        $this->assertDatabaseHas('activity_log', ['action' => 'test.action', 'user_id' => $this->keep->id]);
    }

    public function test_a_journal_role_held_by_only_the_duplicate_is_transferred(): void
    {
        $this->journal->grantRole($this->duplicate, JournalRole::Reviewer);

        $this->merger->merge($this->keep, $this->duplicate);

        $this->assertTrue($this->keep->hasRoleOn($this->journal, JournalRole::Reviewer));
    }

    public function test_a_journal_role_held_by_both_is_not_duplicated(): void
    {
        $this->journal->grantRole($this->keep, JournalRole::Editor);
        $this->journal->grantRole($this->duplicate, JournalRole::Editor);

        $this->merger->merge($this->keep, $this->duplicate);

        $this->assertSame(
            1,
            $this->keep->journalRoles()->where('journal_id', $this->journal->id)->where('role', JournalRole::Editor->value)->count()
        );
    }

    public function test_stage_assignment_conflicts_are_dropped_not_duplicated(): void
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->keep->id,
            'stage' => SubmissionStage::Review->value,
        ]);
        StageAssignment::create([
            'submission_id' => $submission->id,
            'user_id' => $this->keep->id,
            'stage' => SubmissionStage::Review->value,
            'role' => JournalRole::Editor->value,
        ]);
        StageAssignment::create([
            'submission_id' => $submission->id,
            'user_id' => $this->duplicate->id,
            'stage' => SubmissionStage::Review->value,
            'role' => JournalRole::Editor->value,
        ]);

        $this->merger->merge($this->keep, $this->duplicate);

        $this->assertSame(
            1,
            StageAssignment::where('submission_id', $submission->id)
                ->where('user_id', $this->keep->id)
                ->where('role', JournalRole::Editor->value)
                ->count()
        );
    }

    public function test_reviewer_profile_conflict_keeps_the_kept_users_profile(): void
    {
        ReviewerProfile::create([
            'journal_id' => $this->journal->id,
            'user_id' => $this->keep->id,
            'interests' => 'Original interests',
        ]);
        ReviewerProfile::create([
            'journal_id' => $this->journal->id,
            'user_id' => $this->duplicate->id,
            'interests' => 'Duplicate interests',
        ]);

        $this->merger->merge($this->keep, $this->duplicate);

        $profile = ReviewerProfile::where('journal_id', $this->journal->id)->where('user_id', $this->keep->id)->sole();
        $this->assertSame('Original interests', $profile->interests);
    }

    public function test_issue_editor_assignment_is_reassigned(): void
    {
        $issue = Issue::create(['journal_id' => $this->journal->id, 'year' => 2026]);
        IssueEditorAssignment::create(['issue_id' => $issue->id, 'user_id' => $this->duplicate->id]);

        $this->merger->merge($this->keep, $this->duplicate);

        $this->assertTrue($issue->isEditedBy($this->keep));
    }

    public function test_merging_logs_to_activity_log(): void
    {
        $this->merger->merge($this->keep, $this->duplicate);

        $this->assertDatabaseHas('activity_log', [
            'action' => 'user.merged',
            'subject_id' => $this->keep->id,
        ]);
    }
}
