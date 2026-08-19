<?php

namespace Alpha\Journal\Oai;

use Alpha\Journal\Oai\Exceptions\OaiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Resumption tokens with a 24h TTL (CLAUDE.md §5.1). Cache-backed rather than a dedicated table —
 * the token is opaque to the client either way, and cache expiry gives the TTL for free.
 */
class ResumptionToken
{
    private const TTL_HOURS = 24;

    private const PREFIX = 'oai:resumption:';

    /** @param array<string, mixed> $state */
    public static function issue(array $state): string
    {
        $token = Str::random(32);
        Cache::put(self::PREFIX.$token, $state, now()->addHours(self::TTL_HOURS));

        return $token;
    }

    /** @return array<string, mixed> */
    public static function resolve(string $token): array
    {
        $state = Cache::get(self::PREFIX.$token);

        if ($state === null) {
            throw OaiException::badResumptionToken();
        }

        return $state;
    }

    public static function expiryDate(): string
    {
        return now()->addHours(self::TTL_HOURS)->toIso8601ZuluString();
    }
}
