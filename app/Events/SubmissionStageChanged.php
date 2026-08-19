<?php

namespace Alpha\Journal\Events;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Models\EditorialDecision;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Every workflow transition emits an event; notifications and AI hooks subscribe to it, never
 * call SubmissionWorkflow directly (CLAUDE.md §3).
 */
class SubmissionStageChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Submission $submission,
        public readonly EditorialDecisionType $decision,
        public readonly EditorialDecision $decisionRecord,
        public readonly User $actor,
    ) {}
}
