<?php

namespace Alpha\Journal\Support;

use Alpha\Journal\Models\Journal;
use RuntimeException;

/**
 * The journal resolved for the current request (CLAUDE.md §2 — "never trust a raw journal_id
 * from the request"). Bound as a singleton and set by ResolveJournalContext middleware; models
 * scoped with BelongsToJournal read this instead of any request input.
 */
class JournalContext
{
    private ?Journal $journal = null;

    public function set(Journal $journal): void
    {
        $this->journal = $journal;
    }

    public function get(): ?Journal
    {
        return $this->journal;
    }

    public function has(): bool
    {
        return $this->journal !== null;
    }

    public function requireJournal(): Journal
    {
        if ($this->journal === null) {
            throw new RuntimeException('No journal resolved in the current request context.');
        }

        return $this->journal;
    }
}
