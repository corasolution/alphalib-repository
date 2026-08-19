<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\ReviewType;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Models\ReviewRound;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Support\Activity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The browser-navigation half of SubmissionController@show (CLAUDE.md §13.2). The XHR/JSON half
 * of the same route is covered separately by AnonymityLeakTest — this file exists to prove the
 * content-negotiation split didn't quietly break either shape, and that the Inertia payload
 * respects the same reviewer-anonymity boundary as the JSON one.
 */
class SubmissionWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $author;

    private User $editor;

    private User $reviewer;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
        $this->author = User::factory()->create(['name' => 'Author Person']);
        $this->editor = User::factory()->create(['name' => 'Editor Person']);
        $this->reviewer = User::factory()->create(['name' => 'Reviewer Real Name']);
        $this->journal->grantRole($this->editor, JournalRole::Editor);

        $this->submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Review->value,
            'review_type' => ReviewType::DoubleAnonymous->value,
        ]);

        StageAssignment::create([
            'submission_id' => $this->submission->id,
            'user_id' => $this->editor->id,
            'stage' => SubmissionStage::Review->value,
            'role' => JournalRole::Editor->value,
        ]);

        $round = ReviewRound::create(['submission_id' => $this->submission->id, 'round_number' => 1]);
        ReviewAssignment::create(['review_round_id' => $round->id, 'reviewer_id' => $this->reviewer->id]);

        Activity::log('submission.decision.send_to_review', $this->submission, 'Sent to review', []);
        $this->submission->editorialDecisions()->create([
            'actor_id' => $this->editor->id,
            'role_used' => JournalRole::Editor->value,
            'decision' => EditorialDecisionType::SendToReview->value,
            'target_stage' => SubmissionStage::Review->value,
        ]);
    }

    public function test_a_normal_browser_navigation_renders_the_inertia_workspace_page(): void
    {
        $response = $this->actingAs($this->editor)->get(
            route('journal.submissions.show', ['journal' => $this->journal->path, 'submission' => $this->submission->id])
        );

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('journal/submissions/show')
            ->where('submission.id', $this->submission->id)
            ->where('submission.stageLabel', 'Review')
            ->has('timeline', 1)
            ->has('decisions', 1)
            ->has('people', 2)
            ->where('can.recordDecision', true)
        );
    }

    public function test_the_inertia_page_hides_reviewer_identity_from_the_author_under_double_anonymous_review(): void
    {
        $response = $this->actingAs($this->author)->get(
            route('journal.submissions.show', ['journal' => $this->journal->path, 'submission' => $this->submission->id])
        );

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('journal/submissions/show')
            ->where('reviewRounds.0.assignments.0.reviewer', null)
        );

        $response->assertDontSee('Reviewer Real Name');
    }

    public function test_the_inertia_page_reveals_reviewer_identity_to_the_editor(): void
    {
        $response = $this->actingAs($this->editor)->get(
            route('journal.submissions.show', ['journal' => $this->journal->path, 'submission' => $this->submission->id])
        );

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reviewRounds.0.assignments.0.reviewer', 'Reviewer Real Name')
        );
    }

    public function test_a_stranger_is_forbidden_on_the_browser_navigation_path_too(): void
    {
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->get(
            route('journal.submissions.show', ['journal' => $this->journal->path, 'submission' => $this->submission->id])
        );

        $response->assertForbidden();
    }

    public function test_the_author_can_view_but_cannot_record_a_decision(): void
    {
        $response = $this->actingAs($this->author)->get(
            route('journal.submissions.show', ['journal' => $this->journal->path, 'submission' => $this->submission->id])
        );

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.recordDecision', false)
            ->where('can.publish', false)
        );
    }
}
