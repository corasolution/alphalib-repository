<?php

namespace Alpha\Journal\DataCite;

use Alpha\Journal\Crossref\DoiPatternGenerator;
use Alpha\Journal\Jobs\DepositToDataciteJob;
use Alpha\Journal\Models\DataciteDeposit;
use Alpha\Journal\Models\Publication;
use InvalidArgumentException;

/**
 * DOI pattern generation is registrar-agnostic — reuses the same DoiPatternGenerator Crossref
 * uses, so a publication keeps one DOI regardless of which registrar actually deposits it.
 */
class DataCiteDepositService
{
    public function __construct(private readonly DoiPatternGenerator $doiGenerator) {}

    public function deposit(Publication $publication): DataciteDeposit
    {
        if ($publication->status !== 'published') {
            throw new InvalidArgumentException('Only published publications can be deposited to DataCite.');
        }

        if (! $publication->doi) {
            $publication->update(['doi' => $this->doiGenerator->generate($publication)]);
        }

        $deposit = DataciteDeposit::create([
            'publication_id' => $publication->id,
            'doi' => $publication->doi,
            'status' => 'pending',
        ]);

        DepositToDataciteJob::dispatch($deposit);

        return $deposit;
    }
}
