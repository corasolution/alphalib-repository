<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Editor quality rating (1-5, private) — CLAUDE.md §11.4 lists this as part of the reviewer
     * performance record. Private means it's never exposed to the reviewer themselves; nothing in
     * this codebase serializes review_assignments to a reviewer-facing resource that includes it
     * (ReviewAssignmentResource doesn't include it at all) — keep it that way.
     */
    public function up(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->unsignedTinyInteger('quality_rating')->nullable()->after('recommendation');
        });
    }

    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropColumn('quality_rating');
        });
    }
};
