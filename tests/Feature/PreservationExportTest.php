<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\PreservationDeposit;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\SubmissionFile;
use Alpha\Journal\Models\User;
use Alpha\Journal\Preservation\BagBuilder;
use Alpha\Journal\Preservation\PreservationExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;
use ZipArchive;

class PreservationExportTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private Submission $submission;

    private Publication $publication;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
        $author = User::factory()->create();
        $issue = Issue::create(['journal_id' => $this->journal->id, 'volume' => 1, 'number' => 1, 'year' => 2026]);
        $this->submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $author->id,
            'stage' => SubmissionStage::Published->value,
            'issue_id' => $issue->id,
        ]);
        $this->publication = $this->submission->publications()->create([
            'title' => ['en' => 'Coral Bleaching Trends'],
            'status' => 'published',
            'published_at' => now(),
            'doi' => '10.5555/demo.v1.i1.a1',
        ]);
        $this->submission->update(['current_publication_id' => $this->publication->id]);
    }

    public function test_the_bag_contains_the_required_bagit_tag_files(): void
    {
        $zipPath = app(BagBuilder::class)->build($this->publication);

        $zip = new ZipArchive;
        $zip->open($zipPath);

        $this->assertNotFalse($zip->locateName('bagit.txt'));
        $this->assertNotFalse($zip->locateName('bag-info.txt'));
        $this->assertNotFalse($zip->locateName('manifest-sha256.txt'));
        $this->assertNotFalse($zip->locateName('tagmanifest-sha256.txt'));
        $this->assertNotFalse($zip->locateName('data/metadata.json'));
        $this->assertStringContainsString('BagIt-Version: 1.0', $zip->getFromName('bagit.txt'));
        $this->assertStringContainsString('10.5555/demo.v1.i1.a1', $zip->getFromName('bag-info.txt'));

        $zip->close();
        unlink($zipPath);
    }

    public function test_the_manifest_checksum_matches_the_actual_payload_contents(): void
    {
        $zipPath = app(BagBuilder::class)->build($this->publication);

        $zip = new ZipArchive;
        $zip->open($zipPath);
        $metadataContents = $zip->getFromName('data/metadata.json');
        $manifest = $zip->getFromName('manifest-sha256.txt');
        $zip->close();
        unlink($zipPath);

        $expectedChecksum = hash('sha256', $metadataContents);
        $this->assertStringContainsString("{$expectedChecksum}  data/metadata.json", $manifest);
    }

    public function test_the_bag_includes_the_current_manuscript_when_one_exists(): void
    {
        Storage::fake('r2');
        $uploader = User::factory()->create();
        SubmissionFile::storeManuscript($this->submission, UploadedFile::fake()->create('manuscript.pdf', 10), $uploader);

        $zipPath = app(BagBuilder::class)->build($this->publication->fresh());

        $zip = new ZipArchive;
        $zip->open($zipPath);
        $this->assertNotFalse($zip->locateName('data/manuscript.pdf'));
        $zip->close();
        unlink($zipPath);
    }

    public function test_export_requires_a_published_publication(): void
    {
        $this->publication->update(['status' => 'draft']);

        $this->expectException(InvalidArgumentException::class);

        app(PreservationExportService::class)->export($this->publication);
    }

    public function test_export_uploads_the_bag_to_r2_and_records_the_deposit(): void
    {
        Storage::fake('r2');

        $deposit = app(PreservationExportService::class)->export($this->publication);

        Storage::disk('r2')->assertExists($deposit->path);
        $this->assertSame(64, strlen($deposit->sha256));
        $this->assertGreaterThan(0, $deposit->size);
        $this->assertSame("preservation/demo/{$this->publication->id}-bag.zip", $deposit->path);
    }

    public function test_exporting_the_same_publication_twice_updates_rather_than_duplicates(): void
    {
        Storage::fake('r2');

        $first = app(PreservationExportService::class)->export($this->publication);
        $second = app(PreservationExportService::class)->export($this->publication);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PreservationDeposit::where('publication_id', $this->publication->id)->count());
    }

    public function test_the_scheduled_command_exports_only_publications_without_a_deposit(): void
    {
        Storage::fake('r2');
        $alreadyDone = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->submission->submitted_by,
            'stage' => SubmissionStage::Published->value,
        ])->publications()->create(['title' => ['en' => 'Already Archived'], 'status' => 'published', 'published_at' => now()]);
        PreservationDeposit::create([
            'publication_id' => $alreadyDone->id, 'disk' => 'r2', 'path' => 'x', 'sha256' => str_repeat('a', 64), 'size' => 1,
        ]);

        $this->artisan('journal:export-preservation-bags')->assertSuccessful();

        $this->assertSame(1, PreservationDeposit::where('publication_id', $this->publication->id)->count());
        $this->assertSame(1, PreservationDeposit::where('publication_id', $alreadyDone->id)->count());
        $this->assertSame(2, PreservationDeposit::count());
    }
}
