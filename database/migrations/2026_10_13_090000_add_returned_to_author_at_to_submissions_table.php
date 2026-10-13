<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is holding the manuscript right now.
 *
 * The stage says where a submission is in the process; it does not say whether anyone is waiting
 * on the editor or on the author. Returning a manuscript for corrections leaves the stage exactly
 * where it was, so without this the editor's queue would keep counting it as needing a decision
 * while it actually sits with the author — the single most common complaint about editorial
 * dashboards that only track stage.
 *
 * Set when an editor returns it, cleared the moment the author uploads a new file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->timestamp('returned_to_author_at')->nullable()->after('stage');
            $table->index(['journal_id', 'returned_to_author_at']);
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex(['journal_id', 'returned_to_author_at']);
            $table->dropColumn('returned_to_author_at');
        });
    }
};
