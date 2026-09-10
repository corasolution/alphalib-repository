<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a page renders its content, chosen in the CMS page editor.
     *
     * Null means prose — the existing behaviour, so every page that already exists is unaffected.
     * 'editorial_board' additionally renders the board card grid beneath the body.
     *
     * A column rather than hardcoding the behaviour against the 'editorial-board' slug: the menu
     * already points at /pages/editorial-board and that URL should keep working, but a page's
     * appearance shouldn't be silently governed by what someone typed in the slug field.
     */
    public function up(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->string('layout')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
