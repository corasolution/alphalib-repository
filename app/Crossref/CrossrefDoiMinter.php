<?php

namespace Alpha\Journal\Crossref;

use Alpha\Journal\Models\CrossrefDeposit;
use Alpha\Journal\Support\DepositSettings;
use Corasoft\AlphaScholar\PersistentIds\DepositReceipt;
use Corasoft\AlphaScholar\PersistentIds\DoiMinter;
use Illuminate\Support\Facades\Http;

class CrossrefDoiMinter implements DoiMinter
{
    public function __construct(private readonly DepositSettings $settings) {}

    public function deposit(string $doi, string $metadataXml): DepositReceipt
    {
        $response = Http::attach('fname', $metadataXml, 'deposit.xml')
            ->post($this->settings->crossrefDepositUrl(), [
                'operation' => 'doMDUpload',
                'login_id' => $this->settings->crossrefUsername(),
                'login_passwd' => $this->settings->crossrefPassword(),
            ]);

        if (! $response->successful()) {
            return new DepositReceipt($doi, 'failed', "HTTP {$response->status()}: {$response->body()}");
        }

        return new DepositReceipt($doi, 'submitted');
    }

    /**
     * Reports our own tracking record (CrossrefDeposit), not a live query against Crossref's
     * separate submission-status query API — that integration isn't built yet.
     */
    public function status(string $depositId): DepositReceipt
    {
        $deposit = CrossrefDeposit::where('doi', $depositId)->latest('id')->first();

        if ($deposit === null) {
            return new DepositReceipt($depositId, 'unknown', 'No deposit found for this DOI.');
        }

        return new DepositReceipt($deposit->doi, $deposit->status, $deposit->last_error);
    }
}
