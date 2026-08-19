<?php

namespace Alpha\Journal\Doaj;

use Illuminate\Support\Facades\Http;

class DoajClient
{
    /** @param array<string, mixed> $payload */
    public function upload(string $apiKey, array $payload): DoajUploadResult
    {
        $url = rtrim(config('services.doaj.base_url'), '/')."/api/articles?api_key={$apiKey}";
        $response = Http::post($url, $payload);

        if (! $response->successful()) {
            throw new DoajException("DOAJ upload failed: HTTP {$response->status()}: {$response->body()}");
        }

        $data = $response->json();

        return new DoajUploadResult(id: $data['id'] ?? '');
    }
}
