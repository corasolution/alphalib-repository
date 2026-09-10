<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Site-level static content pages (Editorial Board, Submission Guidelines, Ethics, Contact,
     * ...) — the destinations for the header menu links a site admin adds via /site/settings.
     * A real page, not another journal_settings text key, because there can be an arbitrary
     * number of them and each needs its own addressable URL (/pages/{slug}).
     */
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_pages');
    }
};
