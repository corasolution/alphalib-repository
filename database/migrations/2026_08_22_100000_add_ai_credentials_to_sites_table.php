<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Provider credentials for the AI layer, so a site admin can set and rotate them without a
     * server-side .env edit (CLAUDE.md §6 — the hosted tier sells AI credits per journal, which
     * is not workable if onboarding a customer means hand-editing a file).
     *
     * One column holding a provider => key map rather than a column per provider: adding a third
     * provider later should not need a migration, and the whole map is encrypted as a unit.
     *
     * `text`, not `string`: the value is stored through Laravel's `encrypted` cast, and the
     * ciphertext of even one ~110-character key comfortably exceeds a 255-char column.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->text('ai_credentials')->nullable()->after('settings');

            // Installation-wide off switch, above each journal's own consent. Nullable rather
            // than default-true so "never configured" stays distinguishable from "explicitly
            // switched off", and the config default keeps applying until someone sets it.
            $table->boolean('ai_enabled')->nullable()->after('ai_credentials');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['ai_credentials', 'ai_enabled']);
        });
    }
};
