<?php

namespace Corasoft\AlphaScholar\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Submission/galley/cover storage with checksums (CLAUDE.md §2, §6 Alpha Scholar Core: Files).
 * Virus scanning is a hook point — wire an antivirus queue job to store() before P1 file uploads
 * go live.
 *
 * Two disks are in play: the local one an install starts on, and the bucket an admin may later
 * configure. Every stored row records which disk holds its bytes, and reads go through that
 * value — see resolver(). Reading through a fixed default instead is what would break an install
 * midway through migrating: half the files have moved, half have not, and only the row knows
 * which is which.
 */
class FileStorage
{
    /** The disk an install writes to before any bucket is configured. */
    public const LOCAL_DISK = 'r2';

    /** The bucket, once Admin → Storage has been filled in. */
    public const CLOUD_DISK = 'files_cloud';

    /**
     * @param  callable(): string  $resolveWriteDisk  which disk new uploads land on. A closure so
     *                                                the credential lookup happens per call rather
     *                                                than being frozen at container-build time,
     *                                                which would make a just-saved bucket require
     *                                                a restart to take effect.
     */
    public function __construct(private $resolveWriteDisk = null) {}

    /** Where a new upload goes right now. */
    public function writeDisk(): string
    {
        $resolver = $this->resolveWriteDisk;

        return $resolver ? ($resolver)() : self::LOCAL_DISK;
    }

    public function store(UploadedFile $file, string $directory): StoredFile
    {
        $disk = $this->writeDisk();
        $sha256 = hash_file('sha256', $file->getRealPath());
        $path = Storage::disk($disk)->putFile($directory, $file);

        if ($path === false) {
            throw new \RuntimeException("Failed to store file on disk [{$disk}].");
        }

        return new StoredFile(
            disk: $disk,
            path: $path,
            originalName: $file->getClientOriginalName(),
            mimeType: $file->getClientMimeType(),
            size: $file->getSize(),
            sha256: $sha256,
        );
    }

    /**
     * Reads go to the disk the row names.
     *
     * A null disk means a row written before this existed, when everything lived on the local
     * one — that is the only safe reading of it, since the bytes cannot be anywhere else.
     */
    public function delete(string $path, ?string $disk = null): bool
    {
        return Storage::disk($disk ?: self::LOCAL_DISK)->delete($path);
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, ?string $disk = null): string
    {
        return Storage::disk($disk ?: self::LOCAL_DISK)->temporaryUrl($path, $expiresAt);
    }

    public function exists(string $path, ?string $disk = null): bool
    {
        return Storage::disk($disk ?: self::LOCAL_DISK)->exists($path);
    }

    public function get(string $path, ?string $disk = null): ?string
    {
        return Storage::disk($disk ?: self::LOCAL_DISK)->get($path);
    }
}
