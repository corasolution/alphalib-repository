<?php

namespace Alpha\Journal\Support;

use Alpha\Journal\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Every editorial state change writes an immutable activity_log row (CLAUDE.md §2 hard rule).
 * Call this from the SubmissionWorkflow event listeners, never from controllers directly.
 */
class Activity
{
    public static function log(string $action, ?Model $subject = null, ?string $description = null, array $properties = []): ActivityLog
    {
        $journalId = app(JournalContext::class)->has()
            ? app(JournalContext::class)->get()->id
            : ($subject->journal_id ?? null);

        return ActivityLog::create([
            'journal_id' => $journalId,
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties,
        ]);
    }
}
