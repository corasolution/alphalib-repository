<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reviewer recommendations: accept / revisions_required / resubmit_for_review /
     * resubmit_elsewhere / decline / see_comments (§3). Anonymity is a security boundary — the
     * reviewer_id must be stripped at the API serializer layer for double-anonymous review,
     * never merely hidden in the frontend (§4). No serializer exists yet; this is a P1 follow-up.
     */
    public function up(): void
    {
        Schema::create('review_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('recommendation')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_assignments');
    }
};
