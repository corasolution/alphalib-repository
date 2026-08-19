<?php

namespace Corasoft\AlphaScholar\Metadata;

use JsonSerializable;

/**
 * A { "en": "...", "km": "..." } language map (CLAUDE.md §3.1).
 * Locale fallback chain is caller-supplied: requested -> journal primary -> site primary.
 */
class MultilingualText implements JsonSerializable
{
    /** @param array<string, string> $values */
    public function __construct(private array $values = []) {}

    public static function fromArray(?array $values): self
    {
        return new self($values ?? []);
    }

    public function get(string ...$localeChain): ?string
    {
        foreach ($localeChain as $locale) {
            if (isset($this->values[$locale]) && $this->values[$locale] !== '') {
                return $this->values[$locale];
            }
        }

        return null;
    }

    public function with(string $locale, string $value): self
    {
        $values = $this->values;
        $values[$locale] = $value;

        return new self($values);
    }

    public function locales(): array
    {
        return array_keys($this->values);
    }

    public function toArray(): array
    {
        return $this->values;
    }

    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
