<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every decision stores actor, role used, timestamp, target state, reason text, and the file
     * versions in scope at that moment (CLAUDE.md §4.1). requires_countersign/countersigned_*
     * implement the optional per-journal require_dual_approval setting — a second editor
     * countersigns before the transition actually applies. Immutable otherwise: no updated_at,
     * and the model blocks generic update() outside of countersign().
     */
    public function up(): void
    {
        Schema::create('editorial_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_round_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_used');
            $table->string('decision');
            $table->string('target_stage');
            $table->text('reason')->nullable();
            $table->boolean('requires_countersign')->default(false);
            $table->foreignId('countersigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('countersigned_at')->nullable();
            $table->jsonb('properties')->default('{}');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['submission_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_decisions');
    }
};
