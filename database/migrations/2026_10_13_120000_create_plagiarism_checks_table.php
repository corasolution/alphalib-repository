<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Similarity screening, recorded against the manuscript it was run on.
 *
 * Deliberately a record rather than an integration. Journals use Turnitin, iThenticate, Crossref
 * Similarity Check or their university's licence, and which one is an institutional decision that
 * changes independently of this platform. What the editorial workflow actually needs is the
 * answer — was it checked, what came back, by whom, and can I see the report — not a client
 * library for one vendor. An integration can fill this table later without changing anything that
 * reads from it.
 *
 * A submission can be checked more than once: the published workflows that have this step also
 * have a re-check after the author's corrections, so this is a history, not a column on
 * submissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plagiarism_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();

            // pending  — sent to the tool, report not back yet
            // passed   — below the journal's threshold, may proceed
            // flagged  — needs an editor's judgement before it goes anywhere
            $table->string('status');
            $table->unsignedTinyInteger('similarity_percent')->nullable();
            $table->string('tool')->nullable();
            $table->text('notes')->nullable();

            // The report itself, when the editor attaches one. Same shape as every other stored
            // file so FileStorage and the local/cloud migration treat it identically.
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->foreignId('checked_by')->constrained('users');
            $table->timestamp('checked_at');
            $table->timestamps();

            // The gate reads "the latest check for this submission", which is this index.
            $table->index(['submission_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plagiarism_checks');
    }
};
