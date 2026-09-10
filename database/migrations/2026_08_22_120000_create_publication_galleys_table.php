<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Article-level galleys — the readable file a reader actually downloads (CLAUDE.md §7:
     * "Article landing page must include ... all galley download links").
     *
     * IssueGalley already exists but is explicitly the FULL-ISSUE file; its own docblock notes
     * "this schema doesn't model per-article galleys". Until now a published article could be
     * listed by title with nothing to read, which is most of the point of publishing it.
     *
     * Attached to the publication rather than the submission because galleys are per-version:
     * a corrected version supersedes its predecessor's PDF and must not inherit it silently.
     */
    public function up(): void
    {
        Schema::create('publication_galleys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();

            // "PDF", "HTML", "XML" — the format label a reader sees on the download button.
            $table->string('label');

            $table->string('disk')->default('r2');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('sha256')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['publication_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_galleys');
    }
};
