<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\ActivityLog;
use Alpha\Journal\Models\EditorialDecision;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Support\Activity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * ActivityLog and EditorialDecision block update()/delete() two different ways: instance-level
 * ($model->update()) was already covered elsewhere. This file covers the gap that was actually
 * still open — Model::where(...)->update()/delete(), which bypasses any instance-level override
 * entirely because Eloquent executes it as raw SQL without hydrating a model or firing events.
 */
class ImmutableRecordsTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $user;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
        $this->user = User::factory()->create();
        $this->submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->user->id,
            'stage' => SubmissionStage::Submission->value,
        ]);
    }

    public function test_bulk_update_on_activity_log_is_blocked(): void
    {
        $log = Activity::log('test.action', $this->submission);

        $this->expectException(LogicException::class);

        ActivityLog::where('id', $log->id)->update(['action' => 'tampered']);
    }

    public function test_bulk_delete_on_activity_log_is_blocked(): void
    {
        $log = Activity::log('test.action', $this->submission);

        $this->expectException(LogicException::class);

        ActivityLog::where('id', $log->id)->delete();
    }

    public function test_instance_delete_on_activity_log_is_blocked(): void
    {
        $log = Activity::log('test.action', $this->submission);

        $this->expectException(LogicException::class);

        $log->delete();
    }

    public function test_bulk_update_on_editorial_decision_is_blocked(): void
    {
        $decision = $this->submission->editorialDecisions()->create([
            'actor_id' => $this->user->id, 'role_used' => 'editor',
            'decision' => EditorialDecisionType::SendToReview->value, 'target_stage' => 'review',
        ]);

        $this->expectException(LogicException::class);

        EditorialDecision::where('id', $decision->id)->update(['reason' => 'tampered']);
    }

    public function test_bulk_delete_on_editorial_decision_is_blocked(): void
    {
        $decision = $this->submission->editorialDecisions()->create([
            'actor_id' => $this->user->id, 'role_used' => 'editor',
            'decision' => EditorialDecisionType::SendToReview->value, 'target_stage' => 'review',
        ]);

        $this->expectException(LogicException::class);

        EditorialDecision::where('id', $decision->id)->delete();
    }

    public function test_instance_delete_on_editorial_decision_is_blocked(): void
    {
        $decision = $this->submission->editorialDecisions()->create([
            'actor_id' => $this->user->id, 'role_used' => 'editor',
            'decision' => EditorialDecisionType::SendToReview->value, 'target_stage' => 'review',
        ]);

        $this->expectException(LogicException::class);

        $decision->delete();
    }

    public function test_countersign_still_works_despite_the_tighter_guard(): void
    {
        $decision = $this->submission->editorialDecisions()->create([
            'actor_id' => $this->user->id, 'role_used' => 'editor',
            'decision' => EditorialDecisionType::Accept->value, 'target_stage' => 'copyediting',
            'requires_countersign' => true,
        ]);
        $secondEditor = User::factory()->create();

        $decision->countersign($secondEditor);

        $this->assertSame($secondEditor->id, $decision->fresh()->countersigned_by);
    }
}
