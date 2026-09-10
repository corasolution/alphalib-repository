<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The copyediting file exchange (OJS Copyediting stage) and an author's revised-manuscript
 * re-upload after a request_revisions decision are the same write path — this test covers who
 * gets to use it (editor, assigned copyeditor, the author) and who doesn't (a stranger, an
 * unassigned copyeditor), plus that the as_manuscript flag actually picks the right storage
 * method (promote-to-current vs. supporting file).
 */
class SubmissionFilesControllerTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $author;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
        $this->author = User::factory()->create();

        $this->submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Copyediting->value,
        ]);
    }

    public function test_the_author_can_upload_a_replacement_manuscript(): void
    {
        Storage::fake('r2');

        $response = $this->actingAs($this->author)->post(
            route('journal.submissions.files.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['file' => UploadedFile::fake()->create('revised.pdf', 100, 'application/pdf'), 'as_manuscript' => true]
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertNotNull($this->submission->currentManuscript);
        $this->assertTrue($this->submission->currentManuscript->is_current_manuscript);
        $this->assertSame(SubmissionStage::Copyediting, $this->submission->currentManuscript->stage);
    }

    public function test_a_supporting_file_does_not_replace_the_current_manuscript(): void
    {
        Storage::fake('r2');
        $this->actingAs($this->author)->post(
            route('journal.submissions.files.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['file' => UploadedFile::fake()->create('manuscript.pdf', 100, 'application/pdf'), 'as_manuscript' => true]
        );

        $this->actingAs($this->author)->post(
            route('journal.submissions.files.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['file' => UploadedFile::fake()->create('figure1.pdf', 50, 'application/pdf'), 'as_manuscript' => false]
        );

        $this->assertSame('manuscript.pdf', $this->submission->fresh()->currentManuscript->original_name);
        $this->assertSame(2, $this->submission->files()->count());
    }

    public function test_an_assigned_copyeditor_can_upload(): void
    {
        Storage::fake('r2');
        $copyeditor = User::factory()->create();
        $this->journal->grantRole($copyeditor, JournalRole::Copyeditor);
        StageAssignment::create([
            'submission_id' => $this->submission->id,
            'user_id' => $copyeditor->id,
            'stage' => SubmissionStage::Copyediting->value,
            'role' => JournalRole::Copyeditor->value,
        ]);

        $response = $this->actingAs($copyeditor)->post(
            route('journal.submissions.files.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['file' => UploadedFile::fake()->create('copyedited.pdf', 100, 'application/pdf')]
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_an_unassigned_copyeditor_cannot_upload(): void
    {
        $copyeditor = User::factory()->create();
        $this->journal->grantRole($copyeditor, JournalRole::Copyeditor);

        $response = $this->actingAs($copyeditor)->post(
            route('journal.submissions.files.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['file' => UploadedFile::fake()->create('copyedited.pdf', 100, 'application/pdf')]
        );

        $response->assertForbidden();
    }

    public function test_a_stranger_cannot_upload(): void
    {
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->post(
            route('journal.submissions.files.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['file' => UploadedFile::fake()->create('sneaky.pdf', 100, 'application/pdf')]
        );

        $response->assertForbidden();
    }

    public function test_an_editor_can_upload(): void
    {
        Storage::fake('r2');
        $editor = User::factory()->create();
        $this->journal->grantRole($editor, JournalRole::Editor);

        $response = $this->actingAs($editor)->post(
            route('journal.submissions.files.store', ['journal' => $this->journal->path, 'submission' => $this->submission->id]),
            ['file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf')]
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }
}
