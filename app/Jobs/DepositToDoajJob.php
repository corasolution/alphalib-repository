<?php

namespace Alpha\Journal\Jobs;

use Alpha\Journal\Doaj\DoajArticleBuilder;
use Alpha\Journal\Doaj\DoajClient;
use Alpha\Journal\Doaj\DoajException;
use Alpha\Journal\Models\DoajDeposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DepositToDoajJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly DoajDeposit $deposit) {}

    public function handle(DoajArticleBuilder $builder, DoajClient $client): void
    {
        $publication = $this->deposit->publication;
        $journal = $publication->submission->journal;
        $apiKey = $journal->setting('doaj_api_key');

        $this->deposit->increment('attempts');

        try {
            $result = $client->upload($apiKey, $builder->build($publication));
        } catch (DoajException $e) {
            $this->deposit->update(['status' => 'failed', 'last_error' => $e->getMessage()]);

            throw $e;
        }

        $this->deposit->update([
            'status' => 'submitted',
            'doaj_article_id' => $result->id,
            'submitted_at' => now(),
            'last_error' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->deposit->update([
            'status' => 'failed',
            'last_error' => $exception?->getMessage() ?? 'Unknown failure',
        ]);
    }
}
