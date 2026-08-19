<?php

namespace Alpha\Journal\Policies\Concerns;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\User;

/**
 * The Auditor role has no write path anywhere in the system (CLAUDE.md §4.2). Every journal-scoped
 * policy should use this in its before() hook so a write ability can never be granted by accident
 * as new abilities are added.
 */
trait DeniesAuditorWrites
{
    protected function denyAuditorWrite(User $user, Journal $journal, string $ability): ?bool
    {
        $isViewAbility = str($ability)->startsWith(['view', 'index']);

        if (! $isViewAbility && $user->hasRoleOn($journal, JournalRole::Auditor)) {
            return false;
        }

        return null;
    }
}
