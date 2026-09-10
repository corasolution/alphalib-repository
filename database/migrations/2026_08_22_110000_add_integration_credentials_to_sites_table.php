<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credentials for outbound integrations — the Brevo SMTP key and the Telegram bot token —
     * so a site admin can set and rotate them without a server-side .env edit.
     *
     * A separate column from ai_credentials on purpose: those keys spend money per request, and
     * keeping them apart means anything handling integration settings never has the billable
     * ones in scope. `text` because the value is stored through Laravel's `encrypted` cast.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->text('integration_credentials')->nullable()->after('ai_credentials');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('integration_credentials');
        });
    }
};
