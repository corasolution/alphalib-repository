<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The full filterable table (§13.4) — one click away from the action-queue dashboard. Each
 * `filter` value must show exactly the submissions the matching queue card counted.
 */
class SubmissionsIndexTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
        $this->editor = User::factory()->create();
        $this->journal->grantRole($this->editor, JournalRole::Editor);
    }

    private function submission(SubmissionStage $stage): Submission
    {
        return Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => $stage->value,
        ]);
    }

    public function test_unfiltered_index_lists_every_submission(): void
    {
        $this->submission(SubmissionStage::Submission);
        $this->submission(SubmissionStage::Published);

        $response = $this->actingAs($this->editor)->get(
            route('journal.submissions.index', ['journal' => $this->journal->path])
        );

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('journal/submissions/index')
            ->where('filter', null)
            ->where('submissions.total', 2)
        );
    }

    public function test_no_editor_assigned_filter_matches_the_queue_count(): void
    {
        $unassigned = $this->submission(SubmissionStage::Submission);

        $assigned = $this->submission(SubmissionStage::Submission);
        StageAssignment::create([
            'submission_id' => $assigned->id,
            'user_id' => $this->editor->id,
            'stage' => SubmissionStage::Submission->value,
            'role' => JournalRole::Editor->value,
        ]);

        $response = $this->actingAs($this->editor)->get(
            route('journal.submissions.index', ['journal' => $this->journal->path, 'filter' => 'no_editor_assigned'])
        );

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filter', 'no_editor_assigned')
            ->where('submissions.total', 1)
            ->where('submissions.data.0.id', $unassigned->id)
        );
    }

    public function test_an_unknown_filter_is_rejected(): void
    {
        $response = $this->actingAs($this->editor)->get(
            route('journal.submissions.index', ['journal' => $this->journal->path, 'filter' => 'not_a_real_filter'])
        );

        $response->assertSessionHasErrors('filter');
    }

    public function test_a_stranger_cannot_view_the_index(): void
    {
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->get(
            route('journal.submissions.index', ['journal' => $this->journal->path])
        );

        $response->assertForbidden();
    }

    public function test_the_list_carries_each_submissions_title(): void
    {
        $submission = $this->submission(SubmissionStage::Submission);
        $publication = Publication::create([
            'submission_id' => $submission->id,
            'version' => 1,
            'title' => ['en' => 'Coral Bleaching in the Gulf of Thailand'],
            'status' => 'draft',
        ]);
        $submission->update(['current_publication_id' => $publication->id]);

        $response = $this->actingAs($this->editor)->get(
            route('journal.submissions.index', ['journal' => $this->journal->path])
        );

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('submissions.data.0.title', 'Coral Bleaching in the Gulf of Thailand')
        );
    }

    public function test_a_submission_with_no_publication_yet_reports_a_null_title(): void
    {
        $this->submission(SubmissionStage::Submission);

        $response = $this->actingAs($this->editor)->get(
            route('journal.submissions.index', ['journal' => $this->journal->path])
        );

        // The UI renders "Untitled submission" for this — a real state, not an error.
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('submissions.data.0.title', null)
        );
    }
}
