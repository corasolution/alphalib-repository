<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Article upload via DOAJ API v4 on publish (CLAUDE.md §5.3). Mirrors crossref_deposits'
     * shape — doaj_article_id is DOAJ's own ID for the record, returned on successful upload.
     */
    public function up(): void
    {
        Schema::create('doaj_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->string('doaj_article_id')->nullable();
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
        Schema::dropIfExists('doaj_deposits');
    }
};
