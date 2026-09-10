<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Corrective: an earlier revision of the previous migration added a single `ai_api_key`
     * column, before this app supported more than one provider. A rollback of that revision
     * did not take cleanly, so this migration reconciles either state to the intended one —
     * a provider => key map, so adding a third provider needs no schema change.
     *
     * Guarded with hasColumn on both sides so it is a no-op on an install that already landed
     * on the intended shape, rather than erroring on a column it cannot find.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            if (! Schema::hasColumn('sites', 'ai_credentials')) {
                $table->text('ai_credentials')->nullable()->after('settings');
            }

            if (! Schema::hasColumn('sites', 'ai_enabled')) {
                $table->boolean('ai_enabled')->nullable()->after('settings');
            }
        });

        // Dropped rather than migrated: it was added minutes ago in development and never held
        // a real credential. Copying a value out of it would be pretending at a data migration
        // that has no data to migrate.
        if (Schema::hasColumn('sites', 'ai_api_key')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->dropColumn('ai_api_key');
            });
        }
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            if (Schema::hasColumn('sites', 'ai_credentials')) {
                $table->dropColumn('ai_credentials');
            }
        });
    }
};
