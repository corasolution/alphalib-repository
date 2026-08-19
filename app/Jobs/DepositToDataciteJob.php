<?php

namespace Alpha\Journal\Jobs;

use Alpha\Journal\DataCite\DataCiteDoiMinter;
use Alpha\Journal\DataCite\DataCiteXmlBuilder;
use Alpha\Journal\Models\DataciteDeposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class DepositToDataciteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly DataciteDeposit $deposit) {}

    public function handle(DataCiteXmlBuilder $xmlBuilder, DataCiteDoiMinter $minter): void
    {
        $xml = $xmlBuilder->build($this->deposit->publication, $this->deposit->doi);
        $receipt = $minter->deposit($this->deposit->doi, $xml);

        $this->deposit->increment('attempts');

        if ($receipt->status === 'failed') {
            $this->deposit->update(['status' => 'failed', 'last_error' => $receipt->message]);

            throw new RuntimeException("DataCite deposit failed for DOI {$this->deposit->doi}: {$receipt->message}");
        }

        $this->deposit->update(['status' => 'submitted', 'submitted_at' => now(), 'last_error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->deposit->update([
            'status' => 'failed',
            'last_error' => $exception?->getMessage() ?? 'Unknown failure',
        ]);
    }
}
