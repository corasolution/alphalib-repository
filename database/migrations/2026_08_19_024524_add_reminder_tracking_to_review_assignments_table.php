<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reviewer reminder before/after due date, and overdue escalation to the assigned editor
     * (CLAUDE.md §11.2). Tracked per-assignment so the scheduled commands are idempotent — a
     * reminder or escalation fires at most once per interval, not once per scheduler tick.
     */
    public function up(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->timestamp('last_reminder_sent_at')->nullable()->after('completed_at');
            $table->timestamp('editor_escalated_at')->nullable()->after('last_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropColumn(['last_reminder_sent_at', 'editor_escalated_at']);
        });
    }
};
