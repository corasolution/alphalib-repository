<?php

namespace Alpha\Journal\Listeners;

use Alpha\Journal\DataCite\DataCiteDepositService;
use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Events\SubmissionStageChanged;

/**
 * Opt-in on two conditions: the journal has a doi_prefix configured (needed to generate a DOI at
 * all) and this installation has DataCite credentials configured at all (services.datacite.username)
 * — without the latter, deposit() would just fail every time, so there's no point queuing it.
 *
 * Deliberately NOT ShouldQueue — same reasoning as DepositToDoaj/DepositToCrossref: the slow work
 * lives in the queued DepositToDataciteJob.
 */
class DepositToDataCite
{
    public function __construct(private readonly DataCiteDepositService $service) {}

    public function handle(SubmissionStageChanged $event): void
    {
        if ($event->decision !== EditorialDecisionType::Publish) {
            return;
        }

        if (! config('services.datacite.username')) {
            return;
        }

        $publication = $event->submission->currentPublication;

        if ($publication === null) {
            return;
        }

        if (! $event->submission->journal->setting('doi_prefix')) {
            return;
        }

        $this->service->deposit($publication);
    }
}
