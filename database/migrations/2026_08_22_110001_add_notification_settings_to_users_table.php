<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user notification preferences and Telegram link (CLAUDE.md §11.1).
     *
     * Preferences become necessary the moment a second channel exists: without them every user
     * gets everything on both channels, or nobody gets Telegram at all. Stored as jsonb rather
     * than a row-per-preference table because it is read on every send and never queried across
     * users.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->jsonb('notification_preferences')->nullable()->after('is_suspended');

            // Telegram's own id for the private chat between this user and the bot. Set only
            // once the user has pressed Start — we cannot message anyone who has not.
            $table->string('telegram_chat_id')->nullable()->after('notification_preferences');
            $table->timestamp('telegram_linked_at')->nullable()->after('telegram_chat_id');

            // One-time token handed out in the t.me deep link and matched when the bot reports
            // the /start. Short-lived, single-use.
            $table->string('telegram_link_token')->nullable()->unique()->after('telegram_linked_at');
            $table->timestamp('telegram_link_expires_at')->nullable()->after('telegram_link_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'notification_preferences', 'telegram_chat_id', 'telegram_linked_at',
                'telegram_link_token', 'telegram_link_expires_at',
            ]);
        });
    }
};
