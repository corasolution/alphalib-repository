<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Publications are versioned (CLAUDE.md §2 hard rule) — never mutate a published record;
     * create a new version and keep the old one resolvable.
     */
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->jsonb('title');
            $table->jsonb('abstract')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('doi')->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publications');
    }
};
