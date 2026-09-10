<?php

namespace Alpha\Journal\Policies;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\User;
use Alpha\Journal\Policies\Concerns\DeniesAuditorWrites;
use Alpha\Journal\Support\JournalContext;

/**
 * Issue assembly (OJS Production stage: "Schedule for Publication") — creating issues, uploading
 * the issue-level galley, and publishing an issue are all Editor/Journal Manager actions, the
 * same authority shape as SubmissionPolicy::publish (§4.1) since this is what actually makes
 * content public. The issues *index* page reuses JournalPolicy::view (any journal member) — this
 * policy only covers the write side.
 */
class IssuePolicy
{
    use DeniesAuditorWrites;

    /**
     * $issue is `mixed`, not `?Issue`, because Gate::authorize('create', Issue::class) — the
     * class-string form used when there's no model instance yet — passes that class-string
     * straight through to every before() callback, not null.
     */
    public function before(User $user, string $ability, mixed $issue = null): ?bool
    {
        if ($user->is_site_admin) {
            return true;
        }

        return $issue instanceof Issue ? $this->denyAuditorWrite($user, $issue->journal, $ability) : null;
    }

    /**
     * Creating an issue has no Issue instance yet — authorized against the journal resolved by
     * the `journal` route middleware for the current request, the same JournalContext every other
     * journal-scoped write path already relies on.
     */
    public function create(User $user): bool
    {
        $journal = app(JournalContext::class)->requireJournal();

        if ($user->hasRoleOn($journal, JournalRole::Auditor)) {
            return false;
        }

        return $user->hasRoleOn($journal, JournalRole::Editor)
            || $user->hasRoleOn($journal, JournalRole::JournalManager);
    }

    public function manage(User $user, Issue $issue): bool
    {
        return $user->hasRoleOn($issue->journal, JournalRole::Editor)
            || $user->hasRoleOn($issue->journal, JournalRole::JournalManager);
    }
}
