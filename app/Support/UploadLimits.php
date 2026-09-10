<?php

namespace Alpha\Journal\Support;

/**
 * How large an upload this installation can actually accept.
 *
 * The app's own policy is one number; what PHP will accept is another, and it is the smaller of
 * the two that decides. Stating the policy figure while PHP silently refuses anything over its
 * own limit is how an author ends up with a raw 413 page and a lost form — the request dies in
 * ValidatePostSize, before any validation rule or friendly message gets a chance to run.
 *
 * So every place that needs a limit — the validation rule, the hint under the file picker, and
 * the browser-side check that stops the upload before it is sent — reads it from here.
 */
class UploadLimits
{
    /** What the journal is willing to accept for a manuscript, before PHP gets a say. */
    public const POLICY_KILOBYTES = 51200;

    /** Images are page furniture, not manuscripts — 5 MB is already generous for one. */
    public const IMAGE_KILOBYTES = 5120;

    /**
     * What an image upload is allowed to be — a logo, a hero slide, a board photo.
     *
     * Deliberately far below the manuscript limit: nothing on a web page needs 50 MB, and a
     * generous cap here just means slow pages and wasted storage. Still floored by what PHP will
     * accept, so it can never promise more than the server can take.
     */
    public static function imageKilobytes(): int
    {
        return (int) min(self::IMAGE_KILOBYTES, self::perFileKilobytes());
    }

    /** The largest single file this install can take, in kilobytes — the unit Laravel's `max:` uses. */
    public static function perFileKilobytes(): int
    {
        return (int) max(1, floor(self::perFileBytes() / 1024));
    }

    public static function perFileBytes(): int
    {
        return min(
            self::POLICY_KILOBYTES * 1024,
            self::iniBytes('upload_max_filesize'),
            // A single file still has to fit inside the whole request body.
            self::postMaxBytes(),
        );
    }

    /**
     * The size of the entire request body PHP will accept, covering every file plus the form
     * fields. Zero in php.ini means unlimited, which we report as the policy figure rather than
     * as no limit at all.
     */
    public static function postMaxBytes(): int
    {
        $post = self::iniBytes('post_max_size');

        return $post > 0 ? $post : self::POLICY_KILOBYTES * 1024;
    }

    /** The per-file limit in words, for a hint an author reads before choosing a file. */
    public static function perFileLabel(): string
    {
        return self::label(self::perFileBytes());
    }

    public static function postMaxLabel(): string
    {
        return self::label(self::postMaxBytes());
    }

    private static function label(int $bytes): string
    {
        $mb = $bytes / (1024 * 1024);

        return $mb >= 10 ? round($mb).' MB' : round($mb, 1).' MB';
    }

    /**
     * Resolves a php.ini shorthand ("2M", "64M", "1G") to bytes.
     *
     * Public and taking a value rather than reading ini_get itself: upload_max_filesize and
     * post_max_size are PHP_INI_PERDIR, so a test cannot set them at runtime to exercise this.
     */
    public static function bytesFromShorthand(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return PHP_INT_MAX;
        }

        $number = (int) $value;
        $unit = strtolower(substr($value, -1));

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private static function iniBytes(string $directive): int
    {
        return self::bytesFromShorthand((string) ini_get($directive));
    }
}
