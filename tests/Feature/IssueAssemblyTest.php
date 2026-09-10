<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * OJS Production stage's "add the submission to a future or back issue" + "Schedule for
 * Publication" — issue creation, assigning a submission to an issue (the prerequisite
 * SubmissionWorkflow::guardIssueAssignedForPublish already enforces), galley upload, and
 * publishing the issue itself.
 */
class IssueAssemblyTest extends TestCase
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

    public function test_an_editor_can_create_an_issue(): void
    {
        $response = $this->actingAs($this->editor)->post(
            route('journal.issues.store', ['journal' => $this->journal->path]),
            ['volume' => 4, 'number' => 2, 'year' => 2026]
        );

        $issue = Issue::sole();
        $response->assertRedirect(route('journal.issues.show', ['journal' => $this->journal->path, 'issue' => $issue->id]));
        $this->assertFalse($issue->is_published);
        $this->assertSame(4, $issue->volume);
    }

    public function test_a_reviewer_cannot_create_an_issue(): void
    {
        $reviewer = User::factory()->create();
        $this->journal->grantRole($reviewer, JournalRole::Reviewer);

        $response = $this->actingAs($reviewer)->post(
            route('journal.issues.store', ['journal' => $this->journal->path]),
            ['volume' => 1]
        );

        $response->assertForbidden();
    }

    public function test_a_submission_can_be_assigned_to_an_issue(): void
    {
        $issue = Issue::create(['journal_id' => $this->journal->id]);
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => SubmissionStage::Production->value,
        ]);

        $response = $this->actingAs($this->editor)->post(
            route('journal.submissions.issue-assignment.store', ['journal' => $this->journal->path, 'submission' => $submission->id]),
            ['issue_id' => $issue->id]
        );

        $response->assertRedirect();
        $this->assertSame($issue->id, $submission->fresh()->issue_id);
    }

    public function test_a_submission_cannot_be_assigned_to_another_journals_issue(): void
    {
        $otherJournal = Journal::create(['site_id' => $this->journal->site_id, 'path' => 'other', 'title' => ['en' => 'Other']]);
        $foreignIssue = Issue::create(['journal_id' => $otherJournal->id]);
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => SubmissionStage::Production->value,
        ]);

        $response = $this->actingAs($this->editor)->post(
            route('journal.submissions.issue-assignment.store', ['journal' => $this->journal->path, 'submission' => $submission->id]),
            ['issue_id' => $foreignIssue->id]
        );

        $response->assertSessionHasErrors('issue_id');
        $this->assertNull($submission->fresh()->issue_id);
    }

    public function test_an_editor_can_upload_a_galley_and_publish_the_issue(): void
    {
        Storage::fake('r2');
        $issue = Issue::create(['journal_id' => $this->journal->id, 'volume' => 1]);

        $galleyResponse = $this->actingAs($this->editor)->post(
            route('journal.issues.galleys.store', ['journal' => $this->journal->path, 'issue' => $issue->id]),
            ['label' => 'PDF', 'file' => UploadedFile::fake()->create('issue.pdf', 500, 'application/pdf')]
        );
        $galleyResponse->assertRedirect();
        $this->assertSame(1, $issue->galleys()->count());

        $publishResponse = $this->actingAs($this->editor)->post(
            route('journal.issues.publish', ['journal' => $this->journal->path, 'issue' => $issue->id])
        );
        $publishResponse->assertRedirect();
        $this->assertTrue($issue->fresh()->is_published);
        $this->assertNotNull($issue->fresh()->publish_at);
    }

    public function test_the_issues_index_shows_counts(): void
    {
        $issue = Issue::create(['journal_id' => $this->journal->id, 'volume' => 1]);
        Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => SubmissionStage::Production->value,
            'issue_id' => $issue->id,
        ]);

        $response = $this->actingAs($this->editor)->get(route('journal.issues.index', ['journal' => $this->journal->path]));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('journal/issues/index')
            ->has('issues', 1)
            ->where('issues.0.submissionCount', 1)
            ->where('can.manage', true)
        );
    }
}
