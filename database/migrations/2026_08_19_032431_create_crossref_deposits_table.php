<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Async deposit queue with status polling and retry (CLAUDE.md §5.2). "Surface deposit status
     * per article in the editor UI — this is a top OJS complaint" — no UI yet, but the status is
     * tracked here so that UI has something real to read from once it exists.
     */
    public function up(): void
    {
        Schema::create('crossref_deposits', function (Blueprint $table) {
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
        Schema::dropIfExists('crossref_deposits');
    }
};
