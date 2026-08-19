<?php

namespace Alpha\Journal\Preservation;

use Alpha\Journal\Models\PreservationDeposit;
use Alpha\Journal\Models\Publication;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class PreservationExportService
{
    public function __construct(private readonly BagBuilder $bagBuilder) {}

    public function export(Publication $publication): PreservationDeposit
    {
        if ($publication->status !== 'published') {
            throw new InvalidArgumentException('Only published publications can be exported for preservation.');
        }

        $zipPath = $this->bagBuilder->build($publication);

        try {
            $sha256 = hash_file('sha256', $zipPath);
            $size = filesize($zipPath);
            $journal = $publication->submission->journal;
            $storagePath = "preservation/{$journal->path}/{$publication->id}-bag.zip";

            Storage::disk('r2')->put($storagePath, file_get_contents($zipPath));

            return PreservationDeposit::updateOrCreate(
                ['publication_id' => $publication->id],
                ['disk' => 'r2', 'path' => $storagePath, 'sha256' => $sha256, 'size' => $size]
            );
        } finally {
            @unlink($zipPath);
        }
    }
}
