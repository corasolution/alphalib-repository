<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Query;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\SubmissionFile;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionFilesAndQueriesTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $author;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'a', 'title' => ['en' => 'Journal A']]);
        $this->author = User::factory()->create();
        $this->submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value,
        ]);
    }

    public function test_storing_a_manuscript_uploads_to_r2_and_promotes_it_to_current(): void
    {
        Storage::fake('r2');
        $upload = UploadedFile::fake()->create('manuscript.pdf', 100, 'application/pdf');

        $file = SubmissionFile::storeManuscript($this->submission, $upload, $this->author);

        $this->assertSame(1, $file->version);
        $this->assertTrue($file->is_current_manuscript);
        Storage::disk('r2')->assertExists($file->path);
        $this->assertSame($this->submission->currentManuscript()->first()->id, $file->id);
    }

    public function test_uploading_a_new_manuscript_versions_up_and_demotes_the_prior_current_file(): void
    {
        Storage::fake('r2');

        $first = SubmissionFile::storeManuscript(
            $this->submission,
            UploadedFile::fake()->create('v1.pdf', 50),
            $this->author
        );
        $second = SubmissionFile::storeManuscript(
            $this->submission,
            UploadedFile::fake()->create('v2.pdf', 60),
            $this->author
        );

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertFalse($first->fresh()->is_current_manuscript);
        $this->assertTrue($second->fresh()->is_current_manuscript);
        $this->assertSame($second->id, $this->submission->currentManuscript()->first()->id);
    }

    public function test_manuscript_upload_records_a_sha256_checksum(): void
    {
        Storage::fake('r2');
        $upload = UploadedFile::fake()->create('manuscript.pdf', 10);

        $file = SubmissionFile::storeManuscript($this->submission, $upload, $this->author);

        $this->assertSame(64, strlen($file->sha256));
        $this->assertSame(hash_file('sha256', $upload->getRealPath()), $file->sha256);
    }

    public function test_a_query_threads_messages_in_chronological_order(): void
    {
        $query = Query::create([
            'submission_id' => $this->submission->id,
            'stage' => SubmissionStage::Review->value,
            'title' => 'Question about Figure 2',
            'created_by' => $this->author->id,
        ]);

        $query->messages()->create(['user_id' => $this->author->id, 'body' => 'first message']);
        $query->messages()->create(['user_id' => $this->author->id, 'body' => 'second message']);

        $this->assertSame(['first message', 'second message'], $query->messages->pluck('body')->all());
        $this->assertSame(1, $this->submission->queries()->count());
    }
}
