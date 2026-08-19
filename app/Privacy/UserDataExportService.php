<?php

namespace Alpha\Journal\Privacy;

use Alpha\Journal\Models\ActivityLog;
use Alpha\Journal\Models\EditorialDecision;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Models\Scopes\JournalScope;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;

/**
 * "User data export (machine-readable, all personal data across submissions and reviews)"
 * (CLAUDE.md §11.5). Bypasses JournalScope explicitly (like ReviewerPerformanceCalculator) since
 * this legitimately spans every journal the user has ever touched, not just one resolved from
 * ambient request context.
 */
class UserDataExportService
{
    /** @return array<string, mixed> */
    public function export(User $user): array
    {
        return [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'orcid' => $user->orcid,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'journal_roles' => $user->journalRoles()->get()->map(fn ($r) => [
                'journal_id' => $r->journal_id,
                'role' => $r->role->value,
                'granted_at' => $r->created_at?->toIso8601String(),
            ])->all(),
            'submissions_authored' => Submission::withoutGlobalScope(JournalScope::class)
                ->where('submitted_by', $user->id)
                ->get(['id', 'journal_id', 'stage', 'submitted_at'])
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'journal_id' => $s->journal_id,
                    'stage' => $s->stage->value,
                    'submitted_at' => $s->submitted_at?->toIso8601String(),
                ])->all(),
            'review_assignments' => ReviewAssignment::where('reviewer_id', $user->id)
                ->get(['id', 'review_round_id', 'recommendation', 'invited_at', 'completed_at'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'review_round_id' => $a->review_round_id,
                    'recommendation' => $a->recommendation?->value,
                    'invited_at' => $a->invited_at?->toIso8601String(),
                    'completed_at' => $a->completed_at?->toIso8601String(),
                ])->all(),
            'editorial_decisions_made' => EditorialDecision::where('actor_id', $user->id)
                ->get(['id', 'submission_id', 'decision', 'created_at'])
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'submission_id' => $d->submission_id,
                    'decision' => $d->decision->value,
                    'created_at' => $d->created_at?->toIso8601String(),
                ])->all(),
            'activity_log' => ActivityLog::where('user_id', $user->id)
                ->get(['id', 'journal_id', 'action', 'created_at'])
                ->map(fn ($entry) => [
                    'id' => $entry->id,
                    'journal_id' => $entry->journal_id,
                    'action' => $entry->action,
                    'created_at' => $entry->created_at?->toIso8601String(),
                ])->all(),
        ];
    }
}
