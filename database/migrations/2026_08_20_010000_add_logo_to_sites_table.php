<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A real file needs a real, reliably-servable path — jsonb Site.settings (used for
     * tagline/about/menu_items/social_links) is right for text, wrong for a file reference that
     * must resolve through FileStorage::temporaryUrl(). Mirrors submission_files/issue_galleys'
     * disk+path shape.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('logo_disk')->nullable()->after('settings');
            $table->string('logo_path')->nullable()->after('logo_disk');
            $table->string('logo_original_name')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['logo_disk', 'logo_path', 'logo_original_name']);
        });
    }
};
