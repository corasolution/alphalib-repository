<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Ai\AiContext;
use LogicException;

/**
 * ImmutableBuilder stops anyone changing a decision after the fact. This stops an AI *making* one
 * in the first place — CLAUDE.md §6 guardrail 1, and the §9 requirement to assert that no AI code
 * path can write to editorial_decisions.
 *
 * Why here and not a `creating` model observer: Model::performInsert() reaches the database
 * through insertGetId() on an auto-incrementing table and insert() otherwise, while a bulk
 * EditorialDecision::insert([...]) runs as raw SQL that never hydrates a model or fires an event
 * at all. Overriding both methods on the builder is the one place every route actually converges
 * — the same reasoning ImmutableBuilder already applies to update() and delete().
 *
 * The guard is on provenance, not on the table: a human editor writes freely, and an AI feature
 * that only ranks, flags, drafts and extracts never touches this table anyway. It fires only when
 * an AI code path tries to cross into deciding.
 */
class EditorialDecisionBuilder extends ImmutableBuilder
{
    public function insert(array $values)
    {
        $this->refuseIfAiOriginated();

        return parent::insert($values);
    }

    public function insertGetId(array $values, $sequence = null)
    {
        $this->refuseIfAiOriginated();

        return parent::insertGetId($values, $sequence);
    }

    private function refuseIfAiOriginated(): void
    {
        if (app(AiContext::class)->isActive()) {
            throw new LogicException(
                'An AI code path attempted to write an editorial decision. AI is advisory only '
                .'(CLAUDE.md §6): it ranks, flags, drafts and extracts, but a human records every decision.'
            );
        }
    }
}
