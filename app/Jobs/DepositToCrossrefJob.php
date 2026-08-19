<?php

namespace Alpha\Journal\Jobs;

use Alpha\Journal\Crossref\CrossrefDoiMinter;
use Alpha\Journal\Crossref\CrossrefXmlBuilder;
use Alpha\Journal\Models\CrossrefDeposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Async deposit queue with retry (CLAUDE.md §5.2).
 */
class DepositToCrossrefJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly CrossrefDeposit $deposit) {}

    public function handle(CrossrefXmlBuilder $xmlBuilder, CrossrefDoiMinter $minter): void
    {
        $xml = $xmlBuilder->build($this->deposit->publication, $this->deposit->doi);
        $receipt = $minter->deposit($this->deposit->doi, $xml);

        $this->deposit->increment('attempts');

        if ($receipt->status === 'failed') {
            $this->deposit->update(['status' => 'failed', 'last_error' => $receipt->message]);

            throw new RuntimeException("Crossref deposit failed for DOI {$this->deposit->doi}: {$receipt->message}");
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
