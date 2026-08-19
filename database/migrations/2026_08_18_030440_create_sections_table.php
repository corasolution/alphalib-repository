<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Articles", "Reviews", "Editorials" (CLAUDE.md §3, §11.3).
     */
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->jsonb('title');
            $table->jsonb('abbreviation')->nullable();
            $table->jsonb('policy')->nullable();
            $table->unsignedInteger('word_limit')->nullable();
            $table->boolean('review_not_required')->default(false);
            $table->boolean('not_in_toc')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
