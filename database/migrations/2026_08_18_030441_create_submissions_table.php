<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Workflow state machine (CLAUDE.md §3):
     * SUBMISSION -> REVIEW -> COPYEDITING -> PRODUCTION -> PUBLISHED, DECLINED from any stage.
     * current_publication_id FK is added in a follow-up migration (circular with publications).
     */
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->string('stage')->default('submission');
            $table->string('review_type')->default('anonymous');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['journal_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
