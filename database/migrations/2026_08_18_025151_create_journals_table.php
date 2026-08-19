<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A journal is a context, not a tenant — journal_id is a scope column (CLAUDE.md §2), enforced
     * everywhere via JournalScope, never a separate database/schema.
     */
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('path')->unique();
            $table->jsonb('title');
            $table->string('primary_locale', 10)->default('en');
            $table->jsonb('installed_locales')->default('["en"]');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->foreign('redirect_journal_id')->references('id')->on('journals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropForeign(['redirect_journal_id']);
        });

        Schema::dropIfExists('journals');
    }
};
