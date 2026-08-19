<?php

namespace Corasoft\AlphaScholar\Identity;

use InvalidArgumentException;

/**
 * A validated ORCID iD (https://info.orcid.org/documentation/identifier-recognition/), e.g. 0000-0002-1825-0097.
 */
final class OrcidId
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));

        if (! preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $normalized)) {
            throw new InvalidArgumentException("Invalid ORCID iD format: {$value}");
        }

        if (! self::checksumValid($normalized)) {
            throw new InvalidArgumentException("Invalid ORCID iD checksum: {$value}");
        }

        $this->value = $normalized;
    }

    private static function checksumValid(string $orcid): bool
    {
        $digits = str_replace('-', '', substr($orcid, 0, -1));
        $total = 0;

        foreach (str_split($digits) as $digit) {
            $total = ($total + (int) $digit) * 2;
        }

        $remainder = $total % 11;
        $checkDigit = (12 - $remainder) % 11;
        $expected = $checkDigit === 10 ? 'X' : (string) $checkDigit;

        return $expected === substr($orcid, -1);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function url(): string
    {
        return "https://orcid.org/{$this->value}";
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
