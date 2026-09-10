<?php

namespace Alpha\Journal\Ai;

use Closure;

/**
 * Marks that an AI code path is currently executing, so guards elsewhere can refuse writes that
 * an AI must never make (CLAUDE.md §6 guardrail 1: "AI never makes an editorial decision").
 *
 * Bound as a singleton and mirrors JournalContext's shape deliberately — same idea, same reason:
 * the guard reads ambient state rather than trusting a flag passed down the call stack, because a
 * flag can simply not be passed. Anything running inside during() is marked, however deep.
 *
 * Nesting is counted rather than boolean so an inner run finishing can't clear the outer run's
 * mark — a real risk once one feature composes another (A2 will call several models per report).
 */
class AiContext
{
    private int $depth = 0;

    public function isActive(): bool
    {
        return $this->depth > 0;
    }

    /**
     * Runs $callback marked as AI-originated. The decrement is in a finally block on purpose: an
     * exception mid-call must not leave the process permanently marked, which would then block
     * legitimate human writes for the rest of the request.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function during(Closure $callback): mixed
    {
        $this->depth++;

        try {
            return $callback();
        } finally {
            $this->depth--;
        }
    }
}
