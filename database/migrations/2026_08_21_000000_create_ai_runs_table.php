<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every AI call, logged (CLAUDE.md §6 guardrail 3). This table does double duty: it is the
     * product's improvement loop — which prompt version produced output humans actually kept —
     * and it is the evidence the Auditor role reads (§4), so it must record failures as faithfully
     * as successes. A run that errored is still a run that touched a manuscript.
     */
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();

            // journal_id is the scope column (§2). Not nullable: there is no such thing as an AI
            // call that isn't attributable to a journal's consent to be processed.
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained()->nullOnDelete();

            $table->string('feature');
            $table->string('model');
            $table->string('prompt_version');
            $table->string('status');

            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);

            // Integer microcents, not a float: money never gets stored as binary floating point,
            // and Haiku-scale per-call costs round to zero in whole cents.
            $table->unsignedBigInteger('cost_microcents')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);

            $table->jsonb('output')->nullable();
            $table->text('error')->nullable();

            // The improvement loop: did a human keep what the model proposed? Null means nobody
            // has judged it yet, which is distinct from rejected.
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['journal_id', 'feature', 'created_at']);
            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};
