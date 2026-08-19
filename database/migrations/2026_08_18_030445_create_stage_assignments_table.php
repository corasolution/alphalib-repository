<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User + role, per stage (CLAUDE.md §3). The SubmissionWorkflow hard rule — "a submission can
     * only advance if the current stage has at least one assigned editor" — is enforced against
     * this table, not against journal-wide role grants.
     */
    public function up(): void
    {
        Schema::create('stage_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stage');
            $table->string('role');
            $table->timestamps();

            $table->unique(['submission_id', 'user_id', 'stage', 'role']);
            $table->index(['submission_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_assignments');
    }
};
