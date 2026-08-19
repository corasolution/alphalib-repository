<?php

namespace Alpha\Journal\Preservation;

use Alpha\Journal\Models\Publication;
use Alpha\Journal\Oai\DublinCoreMapper;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Builds a BagIt 1.0 bag (RFC 8493) for one published article — bagit.txt, bag-info.txt,
 * manifest-sha256.txt, tagmanifest-sha256.txt, and a data/ payload of the article metadata plus
 * its current manuscript file, if one exists (CLAUDE.md §5.5).
 */
class BagBuilder
{
    public function __construct(private readonly DublinCoreMapper $metadataMapper) {}

    /**
     * Returns the path to a temporary zip file — the caller is responsible for uploading it and
     * deleting it afterward.
     */
    public function build(Publication $publication): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'bag_').'.zip';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create bag archive at {$zipPath}.");
        }

        $payload = $this->collectPayloadFiles($publication);

        $manifestLines = [];
        $totalBytes = 0;

        foreach ($payload as $relativePath => $contents) {
            $zip->addFromString("data/{$relativePath}", $contents);
            $manifestLines[] = hash('sha256', $contents)."  data/{$relativePath}";
            $totalBytes += strlen($contents);
        }

        $bagitTxt = "BagIt-Version: 1.0\nTag-File-Character-Encoding: UTF-8\n";
        $bagInfoTxt = $this->buildBagInfo($publication, $totalBytes, count($payload));
        $manifestTxt = implode("\n", $manifestLines)."\n";

        $zip->addFromString('bagit.txt', $bagitTxt);
        $zip->addFromString('bag-info.txt', $bagInfoTxt);
        $zip->addFromString('manifest-sha256.txt', $manifestTxt);

        $tagManifestLines = [
            hash('sha256', $bagitTxt).'  bagit.txt',
            hash('sha256', $bagInfoTxt).'  bag-info.txt',
            hash('sha256', $manifestTxt).'  manifest-sha256.txt',
        ];
        $zip->addFromString('tagmanifest-sha256.txt', implode("\n", $tagManifestLines)."\n");

        $zip->close();

        return $zipPath;
    }

    /** @return array<string, string> relative payload path => raw file contents */
    private function collectPayloadFiles(Publication $publication): array
    {
        $files = [
            'metadata.json' => json_encode(
                $this->metadataMapper->map($publication),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
        ];

        $manuscript = $publication->submission->currentManuscript;

        if ($manuscript !== null) {
            try {
                $contents = Storage::disk($manuscript->disk)->get($manuscript->path);

                if ($contents !== null) {
                    $files[$manuscript->original_name] = $contents;
                }
            } catch (Throwable) {
                // Manuscript disk/file unreachable — the bag still ships with metadata.json;
                // it just isn't a complete preservation copy this round.
            }
        }

        return $files;
    }

    private function buildBagInfo(Publication $publication, int $totalBytes, int $fileCount): string
    {
        $lines = [
            'Source-Organization: '.(config('app.name') ?: 'Alpha Journal'),
            'Bagging-Date: '.now()->toDateString(),
            "Payload-Oxum: {$totalBytes}.{$fileCount}",
        ];

        if ($publication->doi) {
            $lines[] = "External-Identifier: {$publication->doi}";
        }

        return implode("\n", $lines)."\n";
    }
}
