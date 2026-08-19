<?php

namespace Alpha\Journal\Users;

use Alpha\Journal\Models\User;
use Alpha\Journal\Support\Activity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Merge duplicate users, preserving submission and review history (CLAUDE.md §11.4) — required
 * after migration, since OJS installs routinely have the same person registered twice.
 *
 * Reassignments go through the query builder directly, not Eloquent, so this also works on
 * tables backed by immutable models (EditorialDecision, ActivityLog) whose update() is blocked —
 * correcting a foreign key during account consolidation is a data-integrity operation, not the
 * kind of tampering that immutability guard exists to prevent.
 */
class UserMerger
{
    /**
     * Tables where $duplicate can only hold one FK per unique-key tuple alongside $keep — a
     * naive reassignment would violate the unique constraint, so conflicting rows are dropped in
     * favor of $keep's existing row instead.
     *
     * @var array<string, array{column: string, conflictColumns: string[]}>
     */
    private const CONFLICT_AWARE_TABLES = [
        'journal_user_roles' => ['column' => 'user_id', 'conflictColumns' => ['journal_id', 'role']],
        'stage_assignments' => ['column' => 'user_id', 'conflictColumns' => ['submission_id', 'stage', 'role']],
        'issue_editor_assignments' => ['column' => 'user_id', 'conflictColumns' => ['issue_id']],
        'reviewer_profiles' => ['column' => 'user_id', 'conflictColumns' => ['journal_id']],
    ];

    /**
     * Tables with no unique-key risk — a plain reassignment is safe.
     *
     * @var array<string, string[]>
     */
    private const SIMPLE_TABLES = [
        'submissions' => ['submitted_by'],
        'review_assignments' => ['reviewer_id'],
        'editorial_decisions' => ['actor_id', 'countersigned_by'],
        'activity_log' => ['user_id'],
        'submission_files' => ['uploaded_by'],
        'queries' => ['created_by'],
        'query_messages' => ['user_id'],
        'journal_invitations' => ['invited_by', 'accepted_by'],
    ];

    public function merge(User $keep, User $duplicate): void
    {
        if ($keep->id === $duplicate->id) {
            throw new InvalidArgumentException('Cannot merge a user with themselves.');
        }

        DB::transaction(function () use ($keep, $duplicate) {
            foreach (self::CONFLICT_AWARE_TABLES as $table => $spec) {
                $this->reassignOrDrop($table, $spec['column'], $spec['conflictColumns'], $keep, $duplicate);
            }

            foreach (self::SIMPLE_TABLES as $table => $columns) {
                foreach ($columns as $column) {
                    DB::table($table)->where($column, $duplicate->id)->update([$column => $keep->id]);
                }
            }

            Activity::log(
                'user.merged',
                $keep,
                "Merged user #{$duplicate->id} ({$duplicate->email}) into #{$keep->id} ({$keep->email})",
                ['kept_user_id' => $keep->id, 'duplicate_user_id' => $duplicate->id, 'duplicate_email' => $duplicate->email]
            );

            $duplicate->delete();
        });
    }

    /** @param string[] $conflictColumns */
    private function reassignOrDrop(string $table, string $userColumn, array $conflictColumns, User $keep, User $duplicate): void
    {
        $rows = DB::table($table)->where($userColumn, $duplicate->id)->get();

        foreach ($rows as $row) {
            $query = DB::table($table)->where($userColumn, $keep->id);

            foreach ($conflictColumns as $column) {
                $query->where($column, $row->{$column});
            }

            if ($query->exists()) {
                DB::table($table)->where('id', $row->id)->delete();
            } else {
                DB::table($table)->where('id', $row->id)->update([$userColumn => $keep->id]);
            }
        }
    }
}
