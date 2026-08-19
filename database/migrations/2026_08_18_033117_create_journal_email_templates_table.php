<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-journal, per-locale overrides only (CLAUDE.md §11.1) — the shipped default for every
     * key lives in resources/mail/templates/{key}/{locale}.md (same pattern as the AI prompt
     * registry, §6.4: versioned files, never inline in PHP). A Journal Manager "reset to default"
     * is just deleting the override row; "disable" is is_enabled=false, not a delete, so the
     * customization isn't lost if they re-enable it later.
     */
    public function up(): void
    {
        Schema::create('journal_email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('locale', 10);
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['journal_id', 'key', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_email_templates');
    }
};
