<?php

namespace Alpha\Journal\Listeners;

use Alpha\Journal\Doaj\DoajDepositService;
use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Events\SubmissionStageChanged;

/**
 * Article upload via DOAJ API v4 on publish (CLAUDE.md §5.3). DoajDepositService itself is the
 * opt-in gate (no-ops when the journal has no doaj_api_key configured) — this listener just
 * decides *when* to call it.
 *
 * Deliberately NOT ShouldQueue: the actual slow work (the HTTP upload) already lives in the
 * queued DepositToDoajJob. Queuing this listener too would mean that under Queue::fake() the
 * listener itself gets captured as a job before handle() ever runs, silently preventing the
 * nested DepositToDoajJob::dispatch() call inside it from ever firing — a real bug this class
 * shipped with once already, caught by a test that asserted the job was pushed and found it
 * wasn't.
 */
class DepositToDoaj
{
    public function __construct(private readonly DoajDepositService $service) {}

    public function handle(SubmissionStageChanged $event): void
    {
        if ($event->decision !== EditorialDecisionType::Publish) {
            return;
        }

        $publication = $event->submission->currentPublication;

        if ($publication === null) {
            return;
        }

        $this->service->deposit($publication);
    }
}
