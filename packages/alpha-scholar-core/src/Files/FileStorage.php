<?php

namespace Corasoft\AlphaScholar\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * R2-backed submission/galley/cover storage with checksums (CLAUDE.md §2, §6 Alpha Scholar Core: Files).
 * Virus scanning is a hook point — wire an antivirus queue job to store() before P1 file uploads go live.
 */
class FileStorage
{
    public function __construct(private readonly string $disk = 'r2') {}

    public function store(UploadedFile $file, string $directory): StoredFile
    {
        $sha256 = hash_file('sha256', $file->getRealPath());
        $path = Storage::disk($this->disk)->putFile($directory, $file);

        if ($path === false) {
            throw new \RuntimeException("Failed to store file on disk [{$this->disk}].");
        }

        return new StoredFile(
            disk: $this->disk,
            path: $path,
            originalName: $file->getClientOriginalName(),
            mimeType: $file->getClientMimeType(),
            size: $file->getSize(),
            sha256: $sha256,
        );
    }

    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt): string
    {
        return Storage::disk($this->disk)->temporaryUrl($path, $expiresAt);
    }
}
