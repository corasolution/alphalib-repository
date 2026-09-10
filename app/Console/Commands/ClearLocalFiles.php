<?php

namespace Alpha\Journal\Console\Commands;

use Alpha\Journal\Storage\FileMigrator;
use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the local copies of files that have been migrated to the bucket.
 *
 * Separate from the migration on purpose, and refuses to run while anything is still pending: the
 * point of keeping local copies is to have something to fall back on if the bucket turns out to
 * be wrong, and that guarantee is worth nothing if the same command that moves files also removes
 * the originals.
 *
 * Each file is deleted only after being read back from the bucket, so a file that never actually
 * made it survives this command.
 */
class ClearLocalFiles extends Command
{
    protected $signature = 'files:clear-local {--force : Skip the confirmation prompt}';

    protected $description = 'Delete local copies of files already verified on the object storage bucket';

    public function handle(FileMigrator $migrator): int
    {
        $pending = $migrator->pendingCount();

        if ($pending > 0) {
            $this->error("{$pending} file(s) have not been migrated yet. Run files:migrate-to-cloud first.");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Delete every local copy? The bucket becomes the only place these files exist.')) {
            $this->comment('Nothing deleted.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $kept = 0;

        foreach (FileMigrator::SOURCES as [$model, $diskColumn, $pathColumn]) {
            foreach ($model::query()->whereNotNull($pathColumn)->where($diskColumn, FileStorage::CLOUD_DISK)->get() as $row) {
                $path = $row->{$pathColumn};

                // Proof of life on the bucket before removing the last other copy.
                if (! Storage::disk(FileStorage::CLOUD_DISK)->exists($path)) {
                    $this->warn("  kept  {$path} — not readable on the bucket");
                    $kept++;

                    continue;
                }

                if (Storage::disk(FileStorage::LOCAL_DISK)->exists($path)) {
                    Storage::disk(FileStorage::LOCAL_DISK)->delete($path);
                    $deleted++;
                }
            }
        }

        $this->info("Deleted {$deleted} local file(s).".($kept > 0 ? " Kept {$kept} that the bucket could not confirm." : ''));

        return $kept > 0 ? self::FAILURE : self::SUCCESS;
    }
}
