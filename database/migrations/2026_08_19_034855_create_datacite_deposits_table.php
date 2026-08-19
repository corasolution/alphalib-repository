<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DataCite DOI deposit (CLAUDE.md §5.5, §6 Alpha Scholar Core "Persistent IDs"). Mirrors
     * crossref_deposits — a journal typically picks one registrar, but both pipelines can coexist
     * independently since each is opt-in on its own credentials.
     */
    public function up(): void
    {
        Schema::create('datacite_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->string('doi');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['publication_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datacite_deposits');
    }
};
