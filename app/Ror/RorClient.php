<?php

namespace Alpha\Journal\Ror;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Affiliation autocomplete against the ROR API (CLAUDE.md §5.5) — a public API, no credentials
 * required. Used to fill Contributor.ror_id alongside the free-text affiliation.
 */
class RorClient
{
    /** @return RorOrganization[] */
    public function search(string $query): array
    {
        if (trim($query) === '') {
            return [];
        }

        $response = Http::get(rtrim(config('services.ror.base_url'), '/').'/organizations', ['query' => $query]);

        if (! $response->successful()) {
            throw new RorException("ROR search failed: HTTP {$response->status()}");
        }

        return collect($response->json('items', []))
            ->map(fn (array $item) => new RorOrganization(
                rorId: $this->extractRorId($item['id'] ?? ''),
                name: $this->extractDisplayName($item['names'] ?? []),
                country: $item['locations'][0]['geonames_details']['country_name'] ?? null,
            ))
            ->filter(fn (RorOrganization $org) => $org->rorId !== '' && $org->name !== '')
            ->values()
            ->all();
    }

    private function extractRorId(string $idUrl): string
    {
        return Str::afterLast($idUrl, '/');
    }

    /** @param array<int, array{value?: string, types?: string[]}> $names */
    private function extractDisplayName(array $names): string
    {
        foreach ($names as $name) {
            if (in_array('ror_display', $name['types'] ?? [], true)) {
                return $name['value'] ?? '';
            }
        }

        return $names[0]['value'] ?? '';
    }
}
