<?php

namespace Alpha\Journal\Storage;

use Alpha\Journal\Models\BoardMember;
use Alpha\Journal\Models\HeroSlide;
use Alpha\Journal\Models\IssueGalley;
use Alpha\Journal\Models\PreservationDeposit;
use Alpha\Journal\Models\PublicationGalley;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\SubmissionFile;
use Closure;
use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Moves every stored file from the local disk to the configured bucket.
 *
 * Three rules shape all of this:
 *
 * 1. Copy, verify, then update the row — in that order. The row keeps pointing at the local copy
 *    until the bytes are provably readable from the bucket, so an interrupted run leaves every
 *    file reachable rather than half of them pointing at nothing.
 * 2. The local file is never deleted. Migration is not the moment to discover the bucket was
 *    misconfigured; ClearLocalFiles is a separate, deliberate step.
 * 3. Re-runnable. Anything already on the cloud disk is skipped, so a run that died partway
 *    resumes simply by being run again.
 *
 * Checksums are compared where the row stores one. Where it does not — hero slides, board photos,
 * the logo — the byte length is compared instead, which is weaker but still catches a truncated
 * or empty upload, and is all the schema offers.
 */
class FileMigrator
{
    /**
     * Every table holding a file, as [model, disk column, path column].
     *
     * Listed explicitly rather than discovered: a new file-bearing table must be a deliberate
     * addition here, because silently not migrating one is the failure mode that leaves an
     * install half on each disk with nobody the wiser.
     *
     * @var list<array{class-string, string, string}>
     */
    public const SOURCES = [
        [SubmissionFile::class, 'disk', 'path'],
        [PublicationGalley::class, 'disk', 'path'],
        [IssueGalley::class, 'disk', 'path'],
        [HeroSlide::class, 'disk', 'path'],
        [BoardMember::class, 'disk', 'path'],
        [PreservationDeposit::class, 'disk', 'path'],
        [Site::class, 'logo_disk', 'logo_path'],
    ];

    /**
     * @param  Closure(string): void|null  $report  called with a line per file, for a command to echo
     * @return array{moved: int, skipped: int, missing: int, failed: int, errors: list<string>}
     */
    public function migrate(bool $dryRun = false, ?Closure $report = null): array
    {
        $tally = ['moved' => 0, 'skipped' => 0, 'missing' => 0, 'failed' => 0, 'errors' => []];
        $say = $report ?? fn () => null;

        foreach (self::SOURCES as [$model, $diskColumn, $pathColumn]) {
            $rows = $model::query()
                ->whereNotNull($pathColumn)
                ->where(fn ($q) => $q->where($diskColumn, '!=', FileStorage::CLOUD_DISK)->orWhereNull($diskColumn))
                ->get();

            foreach ($rows as $row) {
                $this->migrateRow($row, $diskColumn, $pathColumn, $dryRun, $say, $tally);
            }
        }

        return $tally;
    }

    /** How many files are still on the local disk — what the admin screen shows before you start. */
    public function pendingCount(): int
    {
        $pending = 0;

        foreach (self::SOURCES as [$model, $diskColumn, $pathColumn]) {
            $pending += $model::query()
                ->whereNotNull($pathColumn)
                ->where(fn ($q) => $q->where($diskColumn, '!=', FileStorage::CLOUD_DISK)->orWhereNull($diskColumn))
                ->count();
        }

        return $pending;
    }

    /**
     * @param  array{moved: int, skipped: int, missing: int, failed: int, errors: list<string>}  $tally
     */
    private function migrateRow(
        object $row,
        string $diskColumn,
        string $pathColumn,
        bool $dryRun,
        Closure $say,
        array &$tally,
    ): void {
        $path = $row->{$pathColumn};
        $source = $row->{$diskColumn} ?: FileStorage::LOCAL_DISK;
        $label = class_basename($row).'#'.$row->getKey().' '.$path;

        try {
            if (! Storage::disk($source)->exists($path)) {
                // A row whose bytes are already gone. Reported, never "fixed" — deleting the row
                // would destroy the only remaining record that the file ever existed.
                $tally['missing']++;
                $tally['errors'][] = "{$label}: not found on [{$source}]";
                $say("  missing  {$label}");

                return;
            }

            if (Storage::disk(FileStorage::CLOUD_DISK)->exists($path)) {
                // Already copied by an earlier run that died before updating the row.
                $tally['skipped']++;
                $say("  present  {$label}");

                if (! $dryRun) {
                    $row->forceFill([$diskColumn => FileStorage::CLOUD_DISK])->save();
                }

                return;
            }

            if ($dryRun) {
                $tally['moved']++;
                $say("  would move {$label}");

                return;
            }

            $bytes = Storage::disk($source)->get($path);
            Storage::disk(FileStorage::CLOUD_DISK)->put($path, $bytes);

            $this->verify($row, $path, $bytes, $label);

            // Only now does the row point at the bucket.
            $row->forceFill([$diskColumn => FileStorage::CLOUD_DISK])->save();

            $tally['moved']++;
            $say("  moved    {$label}");
        } catch (Throwable $e) {
            $tally['failed']++;
            $tally['errors'][] = "{$label}: {$e->getMessage()}";
            $say("  FAILED   {$label} — {$e->getMessage()}");
        }
    }

    /**
     * Reads the file back out of the bucket and proves it is the same file.
     *
     * Reading back rather than trusting the write: an S3-compatible endpoint that accepts a PUT
     * and stores something different, or nothing, is exactly the failure this whole procedure
     * exists to survive.
     */
    private function verify(object $row, string $path, string $bytes, string $label): void
    {
        $written = Storage::disk(FileStorage::CLOUD_DISK)->get($path);

        if ($written === null) {
            throw new \RuntimeException('wrote to the bucket but read back nothing');
        }

        $expected = property_exists($row, 'sha256') || isset($row->sha256) ? $row->sha256 : null;

        if (filled($expected) && hash('sha256', $written) !== $expected) {
            throw new \RuntimeException('checksum on the bucket does not match the one recorded at upload');
        }

        if (strlen($written) !== strlen($bytes)) {
            throw new \RuntimeException('size on the bucket does not match the local file');
        }
    }
}
