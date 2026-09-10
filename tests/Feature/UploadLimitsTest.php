<?php

namespace Tests\Feature;

use Alpha\Journal\Support\UploadLimits;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What the app tells an author it can accept has to match what PHP will actually take.
 *
 * The failure this guards against is not subtle: PHP rejects an oversized body inside
 * ValidatePostSize, before any route or validation rule runs, so the author gets a raw 413 page
 * and loses the whole form. An install shipping with `upload_max_filesize = 2M` while the form
 * promised 50 MB did exactly that.
 */
class UploadLimitsTest extends TestCase
{
    public function test_the_per_file_limit_never_exceeds_what_php_will_accept(): void
    {
        $limit = UploadLimits::perFileBytes();

        $this->assertLessThanOrEqual(
            UploadLimits::postMaxBytes(),
            $limit,
            'A single file has to fit inside the request body PHP allows.',
        );

        $this->assertLessThanOrEqual(
            UploadLimits::POLICY_KILOBYTES * 1024,
            $limit,
            'The journal policy is a ceiling, never a floor.',
        );
    }

    public function test_the_validation_unit_is_kilobytes(): void
    {
        // Laravel's `max:` rule on a file is kilobytes; passing bytes would silently allow files
        // a thousand times larger than intended.
        $this->assertSame(
            (int) floor(UploadLimits::perFileBytes() / 1024),
            UploadLimits::perFileKilobytes(),
        );
    }

    public function test_labels_read_as_sizes(): void
    {
        $this->assertMatchesRegularExpression('/^[\d.]+ MB$/', UploadLimits::perFileLabel());
        $this->assertMatchesRegularExpression('/^[\d.]+ MB$/', UploadLimits::postMaxLabel());
    }

    /**
     * The parser is tested directly because upload_max_filesize and post_max_size are
     * PHP_INI_PERDIR — ini_set() silently fails on them, so a test that tried to set one would
     * pass while asserting nothing.
     */
    #[DataProvider('shorthandProvider')]
    public function test_shorthand_ini_values_are_resolved(string $value, int $expected): void
    {
        $this->assertSame($expected, UploadLimits::bytesFromShorthand($value));
    }

    /** @return array<string, array{string, int}> */
    public static function shorthandProvider(): array
    {
        return [
            'megabytes' => ['64M', 64 * 1024 * 1024],
            'lowercase' => ['8m', 8 * 1024 * 1024],
            'kilobytes' => ['512K', 512 * 1024],
            'gigabytes' => ['1G', 1024 * 1024 * 1024],
            'plain bytes' => ['1048576', 1048576],
            'padded' => [' 2M ', 2 * 1024 * 1024],
            // An unset directive means no limit, not a limit of zero — reporting zero would
            // refuse every upload.
            'empty means unlimited' => ['', PHP_INT_MAX],
        ];
    }

    public function test_an_unlimited_post_max_size_reports_the_policy_rather_than_no_limit(): void
    {
        // post_max_size = 0 means unlimited in php.ini. Handing 0 to the browser-side check
        // would refuse every file, so the policy figure stands in.
        $this->assertSame(0, UploadLimits::bytesFromShorthand('0'));
        $this->assertGreaterThan(0, UploadLimits::postMaxBytes());
        $this->assertGreaterThan(0, UploadLimits::perFileBytes());
    }
}
