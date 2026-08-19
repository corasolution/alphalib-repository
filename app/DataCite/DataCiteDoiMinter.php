<?php

namespace Alpha\Journal\DataCite;

use Alpha\Journal\Models\DataciteDeposit;
use Corasoft\AlphaScholar\PersistentIds\DepositReceipt;
use Corasoft\AlphaScholar\PersistentIds\DoiMinter;
use Illuminate\Support\Facades\Http;

/**
 * Second real implementation of the DoiMinter contract sketched in P0 (CrossrefDoiMinter was the
 * first) — proof the interface is a genuine abstraction, not a Crossref-only stub.
 *
 * DataCite's REST API v2 takes metadata as JSON:API with the DOI XML base64-encoded inside
 * `data.attributes.xml` — a different submission shape than Crossref's multipart-form upload,
 * which is exactly why this needed its own class rather than reusing CrossrefDoiMinter.
 */
class DataCiteDoiMinter implements DoiMinter
{
    public function deposit(string $doi, string $metadataXml): DepositReceipt
    {
        $response = Http::withBasicAuth(
            config('services.datacite.username') ?? '',
            config('services.datacite.password') ?? ''
        )->withHeaders(['Content-Type' => 'application/vnd.api+json'])
            ->post(rtrim(config('services.datacite.base_url'), '/').'/dois', [
                'data' => [
                    'type' => 'dois',
                    'attributes' => [
                        'doi' => $doi,
                        'event' => 'publish',
                        'xml' => base64_encode($metadataXml),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return new DepositReceipt($doi, 'failed', "HTTP {$response->status()}: {$response->body()}");
        }

        return new DepositReceipt($doi, 'submitted');
    }

    /**
     * Reports our own tracking record (DataciteDeposit), not a live query against DataCite's own
     * API — that round trip isn't built yet.
     */
    public function status(string $depositId): DepositReceipt
    {
        $deposit = DataciteDeposit::where('doi', $depositId)->latest('id')->first();

        if ($deposit === null) {
            return new DepositReceipt($depositId, 'unknown', 'No deposit found for this DOI.');
        }

        return new DepositReceipt($deposit->doi, $deposit->status, $deposit->last_error);
    }
}
