<?php

namespace Alpha\Journal\Policies;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\User;
use Alpha\Journal\Policies\Concerns\DeniesAuditorWrites;

class JournalPolicy
{
    use DeniesAuditorWrites;

    public function before(User $user, string $ability, ?Journal $journal = null): ?bool
    {
        if ($user->is_site_admin) {
            return true;
        }

        return $journal ? $this->denyAuditorWrite($user, $journal, $ability) : null;
    }

    public function view(User $user, Journal $journal): bool
    {
        return $user->journalRoles()->where('journal_id', $journal->id)->exists();
    }

    public function update(User $user, Journal $journal): bool
    {
        return $user->hasRoleOn($journal, JournalRole::JournalManager);
    }

    /**
     * Publishing is a separate, higher-privilege action from accepting (CLAUDE.md §4.1) — this
     * governs journal-level settings, not the publish-a-submission action, which is
     * SubmissionPolicy::publish() (and SubmissionWorkflow's own authority check, which is the
     * real source of truth).
     */
    public function manageSettings(User $user, Journal $journal): bool
    {
        return $user->hasRoleOn($journal, JournalRole::JournalManager);
    }

    /**
     * Invitation-based enrollment is a Journal Manager action (CLAUDE.md §11.4).
     */
    public function sendInvitations(User $user, Journal $journal): bool
    {
        return $user->hasRoleOn($journal, JournalRole::JournalManager);
    }
}
