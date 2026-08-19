<?php

namespace Alpha\Journal\Console\Commands;

use Alpha\Journal\Models\Publication;
use Alpha\Journal\Preservation\PreservationExportService;
use Illuminate\Console\Command;

/**
 * Scheduled BagIt/OAIS export to R2 as a dark archive (CLAUDE.md §5.5). Idempotent — only
 * publications with no existing preservation_deposits row are exported each run.
 */
class ExportPreservationBags extends Command
{
    protected $signature = 'journal:export-preservation-bags';

    protected $description = 'Export a BagIt preservation package for every published article that does not have one yet.';

    public function handle(PreservationExportService $service): int
    {
        $exported = 0;

        Publication::where('status', 'published')
            ->whereDoesntHave('preservationDeposit')
            ->with('submission.journal')
            ->chunkById(50, function ($publications) use ($service, &$exported) {
                foreach ($publications as $publication) {
                    $service->export($publication);
                    $exported++;
                }
            });

        $this->info("Exported {$exported} preservation bag(s).");

        return self::SUCCESS;
    }
}
