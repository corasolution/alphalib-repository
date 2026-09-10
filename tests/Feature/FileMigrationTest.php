<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\HeroSlide;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\PublicationGalley;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\SubmissionFile;
use Alpha\Journal\Models\User;
use Alpha\Journal\Storage\FileMigrator;
use Alpha\Journal\Support\Secrets\StorageCredentials;
use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\ManuscriptFixtures;
use Tests\TestCase;

/**
 * Moving an install's files from the local disk to a bucket.
 *
 * The thing under test is not really "files arrive" — it is that nothing is ever unreachable
 * while the move is in progress or after it goes wrong. A migration that loses a manuscript is
 * worse than one that never runs, so most of these tests are about interruption and failure.
 */
class FileMigrationTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private Submission $submission;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(FileStorage::LOCAL_DISK);
        Storage::fake(FileStorage::CLOUD_DISK);

        $this->site = Site::create(['title' => 'Test Site']);
        $journal = Journal::create([
            'site_id' => $this->site->id, 'path' => 'demo', 'title' => ['en' => 'Demo'], 'is_enabled' => true,
        ]);

        $author = User::factory()->create();
        $this->submission = Submission::create([
            'journal_id' => $journal->id, 'submitted_by' => $author->id,
            'stage' => SubmissionStage::Submission->value, 'submitted_at' => now(),
        ]);

        $this->dir = sys_get_temp_dir().'/alpha-migrate-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function configureBucket(): void
    {
        $this->site->update(['storage_credentials' => [
            StorageCredentials::ACCESS_KEY_ID => 'key',
            StorageCredentials::SECRET_ACCESS_KEY => 'secret',
            StorageCredentials::BUCKET => 'alpha-test',
            StorageCredentials::ENDPOINT => 'https://example.r2.cloudflarestorage.com',
        ]]);

        // Put the credentials into config first, then re-fake. The provider rebuilds the disk
        // only when the config it would apply differs from what is already there — matching it
        // here is what lets a fake survive, instead of every test in this file trying to reach
        // a real bucket.
        config(['filesystems.disks.'.FileStorage::CLOUD_DISK => app(StorageCredentials::class)->diskConfig()]);
        Storage::fake(FileStorage::CLOUD_DISK);
    }

    private function upload(string $name = 'paper.pdf'): UploadedFile
    {
        $path = ManuscriptFixtures::pdf($this->dir.'/'.$name, 'A Manuscript');

        return new UploadedFile($path, $name, null, null, true);
    }

    private function storeManuscript(): SubmissionFile
    {
        return SubmissionFile::storeManuscript($this->submission, $this->upload(), $this->submission->submittedBy);
    }

    public function test_files_land_on_the_local_disk_when_no_bucket_is_configured(): void
    {
        $file = $this->storeManuscript();

        $this->assertSame(FileStorage::LOCAL_DISK, $file->disk);
        Storage::disk(FileStorage::LOCAL_DISK)->assertExists($file->path);
    }

    public function test_migrating_copies_the_bytes_and_repoints_the_row(): void
    {
        $file = $this->storeManuscript();
        $original = Storage::disk(FileStorage::LOCAL_DISK)->get($file->path);

        $result = app(FileMigrator::class)->migrate();

        $this->assertSame(1, $result['moved']);
        $this->assertSame(FileStorage::CLOUD_DISK, $file->fresh()->disk);
        Storage::disk(FileStorage::CLOUD_DISK)->assertExists($file->path);
        $this->assertSame($original, Storage::disk(FileStorage::CLOUD_DISK)->get($file->path));
    }

    /**
     * The whole reason local copies are kept. If the bucket turns out to be wrong, the bytes are
     * still on the server — and this is a separate, explicit command away from being deleted.
     */
    public function test_the_local_copy_survives_the_migration(): void
    {
        $file = $this->storeManuscript();

        app(FileMigrator::class)->migrate();

        Storage::disk(FileStorage::LOCAL_DISK)->assertExists($file->path);
    }

    public function test_a_dry_run_copies_nothing_and_changes_nothing(): void
    {
        $file = $this->storeManuscript();

        $result = app(FileMigrator::class)->migrate(dryRun: true);

        $this->assertSame(1, $result['moved'], 'A dry run still reports what it would do.');
        $this->assertSame(FileStorage::LOCAL_DISK, $file->fresh()->disk);
        Storage::disk(FileStorage::CLOUD_DISK)->assertMissing($file->path);
    }

    public function test_running_twice_does_not_move_anything_the_second_time(): void
    {
        $this->storeManuscript();

        app(FileMigrator::class)->migrate();
        $second = app(FileMigrator::class)->migrate();

        $this->assertSame(0, $second['moved']);
        $this->assertSame(0, $second['failed']);
        $this->assertSame(0, app(FileMigrator::class)->pendingCount());
    }

    /**
     * The interrupted-run case: the bytes reached the bucket but the process died before the row
     * was updated. Re-running has to notice and finish the job rather than copy over itself.
     */
    public function test_a_row_left_behind_by_an_interrupted_run_is_repointed_not_recopied(): void
    {
        $file = $this->storeManuscript();
        Storage::disk(FileStorage::CLOUD_DISK)->put($file->path, Storage::disk(FileStorage::LOCAL_DISK)->get($file->path));

        $result = app(FileMigrator::class)->migrate();

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['moved']);
        $this->assertSame(FileStorage::CLOUD_DISK, $file->fresh()->disk);
    }

    /**
     * A row whose bytes are already gone is reported, never "cleaned up". Deleting it would
     * destroy the only surviving record that the file existed at all.
     */
    public function test_a_row_whose_file_is_missing_is_reported_and_left_alone(): void
    {
        $file = $this->storeManuscript();
        Storage::disk(FileStorage::LOCAL_DISK)->delete($file->path);

        $result = app(FileMigrator::class)->migrate();

        $this->assertSame(1, $result['missing']);
        $this->assertSame(0, $result['moved']);
        $this->assertNotNull($file->fresh(), 'The row must survive.');
        $this->assertSame(FileStorage::LOCAL_DISK, $file->fresh()->disk, 'It must keep pointing where it was.');
        $this->assertStringContainsString('not found', $result['errors'][0]);
    }

    public function test_every_kind_of_stored_file_is_migrated(): void
    {
        // A table missed from FileMigrator::SOURCES leaves that file type stranded on the local
        // disk forever, with nothing to indicate it happened.
        $this->storeManuscript();

        $publication = Publication::create([
            'submission_id' => $this->submission->id, 'version' => 1,
            'title' => ['en' => 'A Paper'], 'status' => 'draft',
        ]);
        PublicationGalley::storeFor($publication, $this->upload('galley.pdf'), 'PDF');
        HeroSlide::storeFor($this->site, $this->upload('slide.pdf'));
        $this->site->storeLogo($this->upload('logo.pdf'));

        $this->assertSame(4, app(FileMigrator::class)->pendingCount());

        $result = app(FileMigrator::class)->migrate();

        $this->assertSame(4, $result['moved']);
        $this->assertSame(0, app(FileMigrator::class)->pendingCount());
        $this->assertSame(FileStorage::CLOUD_DISK, $this->site->fresh()->logo_disk);
    }

    public function test_a_download_url_follows_the_file_to_the_bucket(): void
    {
        // The point of recording the disk per row: reads have to go where the bytes actually are,
        // and during a migration that differs from row to row.
        $slide = HeroSlide::storeFor($this->site, $this->upload('slide.pdf'));

        $this->assertStringContainsString($slide->path, $slide->url());

        app(FileMigrator::class)->migrate();

        $this->assertSame(FileStorage::CLOUD_DISK, $slide->fresh()->disk);
        $this->assertStringContainsString($slide->path, $slide->fresh()->url());
    }

    public function test_the_command_refuses_to_run_without_a_bucket(): void
    {
        $this->storeManuscript();

        $this->artisan('files:migrate-to-cloud')
            ->expectsOutputToContain('No bucket is configured')
            ->assertExitCode(1);

        $this->assertSame(1, app(FileMigrator::class)->pendingCount());
    }

    public function test_the_command_migrates_when_a_bucket_is_configured(): void
    {
        $this->configureBucket();
        $file = $this->storeManuscript();

        $this->artisan('files:migrate-to-cloud')->assertExitCode(0);

        $this->assertSame(FileStorage::CLOUD_DISK, $file->fresh()->disk);
    }

    public function test_clearing_local_copies_is_refused_while_anything_is_still_pending(): void
    {
        $file = $this->storeManuscript();

        $this->artisan('files:clear-local', ['--force' => true])
            ->expectsOutputToContain('have not been migrated yet')
            ->assertExitCode(1);

        Storage::disk(FileStorage::LOCAL_DISK)->assertExists($file->path);
    }

    public function test_clearing_local_copies_removes_them_once_everything_has_moved(): void
    {
        $file = $this->storeManuscript();
        app(FileMigrator::class)->migrate();

        $this->artisan('files:clear-local', ['--force' => true])->assertExitCode(0);

        Storage::disk(FileStorage::LOCAL_DISK)->assertMissing($file->path);
        Storage::disk(FileStorage::CLOUD_DISK)->assertExists($file->path);
    }

    public function test_a_local_copy_is_kept_when_the_bucket_cannot_confirm_the_file(): void
    {
        $file = $this->storeManuscript();
        app(FileMigrator::class)->migrate();

        // The bucket loses it — an expired lifecycle rule, a wrong prefix, a bad restore.
        Storage::disk(FileStorage::CLOUD_DISK)->delete($file->path);

        $this->artisan('files:clear-local', ['--force' => true])->assertExitCode(1);

        Storage::disk(FileStorage::LOCAL_DISK)->assertExists($file->path);
    }
}
