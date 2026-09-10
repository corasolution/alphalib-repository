<?php

namespace Alpha\Journal\Console\Commands;

use Alpha\Journal\Storage\FileMigrator;
use Alpha\Journal\Support\Secrets\StorageCredentials;
use Illuminate\Console\Command;

/**
 * Moves stored files from the local disk to the configured bucket.
 *
 * Safe to run repeatedly: anything already on the bucket is skipped, so an interrupted run is
 * resumed by running it again. Local copies are left in place — `files:clear-local` removes them
 * once you are satisfied, and that is deliberately a separate decision.
 */
class MigrateFilesToCloud extends Command
{
    protected $signature = 'files:migrate-to-cloud {--dry-run : List what would move without copying anything}';

    protected $description = 'Copy stored files from the local disk to the configured object storage bucket';

    public function handle(FileMigrator $migrator, StorageCredentials $credentials): int
    {
        if (! $credentials->isConfigured()) {
            $this->error('No bucket is configured. Set one in Admin → Storage, or in .env, before migrating.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $pending = $migrator->pendingCount();

        if ($pending === 0) {
            $this->info('Every stored file is already on the bucket. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? 'Dry run: ' : '').$pending.' file(s) to move to bucket ['.$credentials->for(StorageCredentials::BUCKET).'].');
        $this->newLine();

        $result = $migrator->migrate($dryRun, fn (string $line) => $this->line($line));

        $this->newLine();
        $this->table(
            ['Moved', 'Already there', 'Missing', 'Failed'],
            [[$result['moved'], $result['skipped'], $result['missing'], $result['failed']]],
        );

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        if ($dryRun) {
            $this->comment('Nothing was copied. Re-run without --dry-run to move the files.');

            return self::SUCCESS;
        }

        if ($result['failed'] > 0) {
            $this->error('Some files did not move. They still point at the local disk and are still readable — fix the cause and run this again.');

            return self::FAILURE;
        }

        $this->info('Done. Local copies were left in place; run files:clear-local once you have verified the bucket.');

        return self::SUCCESS;
    }
}
