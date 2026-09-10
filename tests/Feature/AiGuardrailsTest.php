<?php

namespace Tests\Feature;

use Alpha\Journal\Ai\AiContext;
use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\EditorialDecision;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * CLAUDE.md §6 guardrail 1 — "AI never makes an editorial decision" — and §9's matching
 * requirement to "assert no AI code path can write to editorial_decisions".
 *
 * The point of these tests is that the guarantee must be structural, not a code-review habit.
 * They deliberately attack every write path into the table rather than the one a well-behaved
 * feature would use, because the threat model is a future AI feature doing something careless.
 */
class AiGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    private Submission $submission;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo']]);
        $this->editor = User::factory()->create();
        $journal->grantRole($this->editor, JournalRole::Editor);

        $this->submission = Submission::create([
            'journal_id' => $journal->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => SubmissionStage::Review->value,
        ]);
    }

    /** @return array<string, mixed> */
    private function decisionAttributes(): array
    {
        return [
            'submission_id' => $this->submission->id,
            'actor_id' => $this->editor->id,
            'role_used' => JournalRole::Editor->value,
            'decision' => EditorialDecisionType::Accept->value,
            'target_stage' => SubmissionStage::Copyediting->value,
            'reason' => 'The model thought this was a good paper.',
        ];
    }

    public function test_an_ai_code_path_cannot_create_an_editorial_decision(): void
    {
        $this->expectException(LogicException::class);

        app(AiContext::class)->during(fn () => EditorialDecision::create($this->decisionAttributes()));
    }

    public function test_the_bulk_insert_path_is_blocked_too(): void
    {
        // The case a model-level `creating` observer would miss entirely: bulk insert runs as raw
        // SQL without hydrating a model or firing events.
        try {
            app(AiContext::class)->during(fn () => EditorialDecision::insert([$this->decisionAttributes()]));
            $this->fail('Bulk insert from an AI context should have been blocked.');
        } catch (LogicException) {
            // expected
        }

        $this->assertSame(0, EditorialDecision::count());
    }

    public function test_nothing_is_written_when_the_guard_fires(): void
    {
        try {
            app(AiContext::class)->during(fn () => EditorialDecision::create($this->decisionAttributes()));
        } catch (LogicException) {
            // expected
        }

        $this->assertSame(0, EditorialDecision::count());
    }

    public function test_a_human_can_still_record_a_decision(): void
    {
        // The guard must be narrow: it fires on AI provenance, not on the table in general.
        // If this breaks, the guard has locked editors out of their own workflow.
        $decision = EditorialDecision::create($this->decisionAttributes());

        $this->assertTrue($decision->exists);
        $this->assertSame(1, EditorialDecision::count());
    }

    public function test_the_mark_is_cleared_after_the_ai_call_finishes(): void
    {
        app(AiContext::class)->during(fn () => true);

        $this->assertFalse(app(AiContext::class)->isActive());
        $this->assertTrue(EditorialDecision::create($this->decisionAttributes())->exists);
    }

    public function test_the_mark_is_cleared_even_when_the_ai_call_throws(): void
    {
        // Otherwise one failed AI call would block every human decision for the rest of the
        // request — a guard that causes an outage is worse than no guard.
        try {
            app(AiContext::class)->during(fn () => throw new LogicException('model exploded'));
        } catch (LogicException) {
            // expected
        }

        $this->assertFalse(app(AiContext::class)->isActive());
        $this->assertTrue(EditorialDecision::create($this->decisionAttributes())->exists);
    }

    public function test_a_nested_ai_call_finishing_does_not_unmark_the_outer_one(): void
    {
        $ai = app(AiContext::class);

        $ai->during(function () use ($ai) {
            $ai->during(fn () => true);

            $this->assertTrue($ai->isActive(), 'Inner call finishing must not clear the outer mark.');
        });

        $this->assertFalse($ai->isActive());
    }
}
