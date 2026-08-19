<?php

namespace Alpha\Journal\Doaj;

use Alpha\Journal\Jobs\DepositToDoajJob;
use Alpha\Journal\Models\DoajDeposit;
use Alpha\Journal\Models\Publication;
use InvalidArgumentException;

/**
 * Opt-in per journal via journal_settings.doaj_api_key — most journals aren't DOAJ-indexed, so
 * deposit() is a silent no-op (returns null) rather than an error when unconfigured.
 */
class DoajDepositService
{
    public function deposit(Publication $publication): ?DoajDeposit
    {
        if ($publication->status !== 'published') {
            throw new InvalidArgumentException('Only published publications can be deposited to DOAJ.');
        }

        $journal = $publication->submission->journal;

        if (! $journal->setting('doaj_api_key')) {
            return null;
        }

        $deposit = DoajDeposit::create([
            'publication_id' => $publication->id,
            'status' => 'pending',
        ]);

        DepositToDoajJob::dispatch($deposit);

        return $deposit;
    }
}
