<?php

namespace Alpha\Journal\Crossref;

use Alpha\Journal\Jobs\DepositToCrossrefJob;
use Alpha\Journal\Models\CrossrefDeposit;
use Alpha\Journal\Models\Publication;
use InvalidArgumentException;

/**
 * Assigns a DOI if the publication doesn't already have one, then queues the deposit
 * (CLAUDE.md §5.2). Models create records; this service owns the one side effect
 * (dispatching the job) — same separation used throughout (SubmissionWorkflow, invitations).
 */
class CrossrefDepositService
{
    public function __construct(private readonly DoiPatternGenerator $doiGenerator) {}

    public function deposit(Publication $publication): CrossrefDeposit
    {
        if ($publication->status !== 'published') {
            throw new InvalidArgumentException('Only published publications can be deposited to Crossref.');
        }

        if (! $publication->doi) {
            $publication->update(['doi' => $this->doiGenerator->generate($publication)]);
        }

        $deposit = CrossrefDeposit::create([
            'publication_id' => $publication->id,
            'doi' => $publication->doi,
            'status' => 'pending',
        ]);

        DepositToCrossrefJob::dispatch($deposit);

        return $deposit;
    }
}
