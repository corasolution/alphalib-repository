<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scheduled BagIt/OAIS export to R2 as an institutional dark archive (CLAUDE.md §5.5) — "we
     * cannot join PKP PN [...] ship an equivalent, not a gap." No status/retry columns like
     * crossref_deposits/doaj_deposits: this is a local file operation, not a network deposit, so
     * there's no partial-failure state to track — it either wrote the bag or it didn't.
     */
    public function up(): void
    {
        Schema::create('preservation_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->unique('publication_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preservation_deposits');
    }
};
