<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versioned, stage-tagged (§3). CLAUDE.md §13.3 — "a submission has one current manuscript at
     * all times, plus a version history strip... Stage association is metadata on the version, not
     * a separate bucket the user navigates" — hence `stage` + `version` columns here rather than
     * separate per-stage tables (no "Review Files / Copyedited / Production Ready" buckets).
     */
    public function up(): void
    {
        Schema::create('submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('stage');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current_manuscript')->default(false);
            $table->string('disk')->default('r2');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_files');
    }
};
