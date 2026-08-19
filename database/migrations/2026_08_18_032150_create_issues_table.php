<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Issue-level galley (full-issue PDF) and issue-level DOI, cover, TOC ordering (§11.8).
     * TOC/section ordering within the issue is left to a `sort_order` on submissions once the
     * issue-assembly UI exists — not needed for the publish gate itself.
     */
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('volume')->nullable();
            $table->unsignedInteger('number')->nullable();
            $table->unsignedInteger('year')->nullable();
            $table->jsonb('title')->nullable();
            $table->jsonb('description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('publish_at')->nullable();
            $table->string('doi')->nullable();
            $table->timestamps();

            $table->index(['journal_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
