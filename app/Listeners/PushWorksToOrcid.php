<?php

namespace Alpha\Journal\Listeners;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Events\SubmissionStageChanged;
use Alpha\Journal\Models\User;
use Alpha\Journal\Orcid\OrcidClient;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Push works to ORCID record on publish (CLAUDE.md §5.5). Only contributors who are both a
 * registered platform User (matched by ORCID iD) and have completed the OAuth connect flow get
 * a push — a manuscript co-author's typed-in ORCID with no linked, authenticated account is
 * correctly skipped, not an error: most co-authors never create an account here at all.
 */
class PushWorksToOrcid implements ShouldQueue
{
    public function __construct(private readonly OrcidClient $client) {}

    public function handle(SubmissionStageChanged $event): void
    {
        if ($event->decision !== EditorialDecisionType::Publish) {
            return;
        }

        $publication = $event->submission->currentPublication;

        if ($publication === null) {
            return;
        }

        foreach ($publication->contributors as $contributor) {
            if (! $contributor->orcid) {
                continue;
            }

            $user = User::where('orcid', $contributor->orcid)->first();

            if ($user === null || ! $user->hasAuthenticatedOrcid()) {
                continue;
            }

            $this->client->pushWork($user, $publication);
        }
    }
}
