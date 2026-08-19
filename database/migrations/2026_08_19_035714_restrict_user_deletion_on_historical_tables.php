<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every one of these FKs was originally cascadeOnDelete() (or, for activity_log, nullOnDelete)
     * — reasonable in isolation when each migration was written, but wrong in aggregate: a hard
     * $user->delete() would have cascaded away submissions, review history, editorial decisions
     * (the compliance-critical audit trail — CLAUDE.md §2, §4.2), file provenance, and discussion
     * history. Account deletion is now anonymization (AccountAnonymizationService), never a row
     * delete, and this migration makes that structural, not just conventional: the DB itself
     * refuses to delete a user who has any of this history, regardless of what future code path
     * might call ->delete() directly.
     */
    private const RESTRICTED = [
        'submissions' => 'submitted_by',
        'review_assignments' => 'reviewer_id',
        'editorial_decisions' => 'actor_id',
        'activity_log' => 'user_id',
        'submission_files' => 'uploaded_by',
        'queries' => 'created_by',
        'query_messages' => 'user_id',
        'journal_invitations' => 'invited_by',
    ];

    public function up(): void
    {
        foreach (self::RESTRICTED as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
                $blueprint->foreign($column)->references('id')->on('users')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->foreign('submitted_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropForeign(['reviewer_id']);
            $table->foreign('reviewer_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('editorial_decisions', function (Blueprint $table) {
            $table->dropForeign(['actor_id']);
            $table->foreign('actor_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('submission_files', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by']);
            $table->foreign('uploaded_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('queries', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('query_messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('journal_invitations', function (Blueprint $table) {
            $table->dropForeign(['invited_by']);
            $table->foreign('invited_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
