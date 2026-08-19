<?php

namespace Alpha\Journal\Listeners;

use Alpha\Journal\Crossref\CrossrefDepositService;
use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Events\SubmissionStageChanged;

/**
 * Async deposit queue on publish (CLAUDE.md §5.2) — this was the missing link: CrossrefDepositService
 * existed and worked when called directly, but nothing ever called it automatically. Opt-in gate
 * lives here, not in the service: CrossrefDepositService::deposit() still throws on a missing
 * doi_prefix for callers who invoke it directly (e.g. a manual re-deposit action) and want a real
 * error, not a silent no-op — but an unconfigured journal publishing an article should never
 * crash the publish transition itself.
 *
 * Deliberately NOT ShouldQueue — same reasoning as DepositToDoaj: the slow work already lives in
 * the queued DepositToCrossrefJob, and queuing this listener too would silently break under
 * Queue::fake() (that bug already shipped once, in DepositToDoaj).
 */
class DepositToCrossref
{
    public function __construct(private readonly CrossrefDepositService $service) {}

    public function handle(SubmissionStageChanged $event): void
    {
        if ($event->decision !== EditorialDecisionType::Publish) {
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
