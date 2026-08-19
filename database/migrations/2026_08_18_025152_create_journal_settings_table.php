<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extensible per-journal config (author guidelines, checklist items, DOI prefix, etc. — §11.3)
     * that doesn't warrant its own column. locale is '' (not null — Postgres treats NULLs as
     * distinct in unique constraints) for non-multilingual settings.
     */
    public function up(): void
    {
        Schema::create('journal_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('locale', 10)->default('');
            $table->jsonb('value')->nullable();
            $table->timestamps();

            $table->unique(['journal_id', 'name', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_settings');
    }
};
