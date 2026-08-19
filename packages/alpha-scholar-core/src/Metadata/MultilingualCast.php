<?php

namespace Corasoft\AlphaScholar\Metadata;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a JSONB language-map column to/from MultilingualText.
 * Never render the raw JSON blob (CLAUDE.md §3.1) — read through get().
 */
class MultilingualCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): MultilingualText
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return MultilingualText::fromArray(is_array($decoded) ? $decoded : []);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $values = $value instanceof MultilingualText ? $value->toArray() : (array) $value;

        return [$key => json_encode($values, JSON_UNESCAPED_UNICODE)];
    }
}
