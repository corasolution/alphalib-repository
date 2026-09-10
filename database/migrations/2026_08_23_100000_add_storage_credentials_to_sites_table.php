<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Object-storage credentials, so an admin can move the install from local disk to R2 without
     * a server-side .env edit.
     *
     * Its own column rather than sharing integration_credentials: those are outbound messaging
     * secrets, and anything handling storage settings has no business holding the mail key. The
     * bucket, endpoint and region live here too — they are not secrets, but splitting one
     * connection across two stores buys nothing and invites a half-configured disk.
     *
     * `text` because the value is written through Laravel's `encrypted` cast.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->text('storage_credentials')->nullable()->after('integration_credentials');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('storage_credentials');
        });
    }
};
