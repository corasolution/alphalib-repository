<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles are per-journal, not global (CLAUDE.md §4) — a user can be Author on Journal A and
     * Editor on Journal B. Site Admin is the one global role, stored on users.is_site_admin.
     */
    public function up(): void
    {
        Schema::create('journal_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['journal_id', 'user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_user_roles');
    }
};
