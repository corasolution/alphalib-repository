<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ORCID OAuth authenticate flow, not just a typed-in ID (CLAUDE.md §5.5). Store an
     * authenticated flag (orcid_authenticated_at) and the access token needed to push works to
     * the user's ORCID record on publish. The token is encrypted at rest via the model cast.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('orcid', 19)->nullable()->after('suspended_at');
            $table->text('orcid_access_token')->nullable()->after('orcid');
            $table->timestamp('orcid_token_expires_at')->nullable()->after('orcid_access_token');
            $table->timestamp('orcid_authenticated_at')->nullable()->after('orcid_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['orcid', 'orcid_access_token', 'orcid_token_expires_at', 'orcid_authenticated_at']);
        });
    }
};
