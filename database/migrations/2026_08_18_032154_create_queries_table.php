<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Threaded discussion, per stage (§3). Labeled "Discussion" in the UI (§13.8) — the "Query"
     * name is kept in the schema/API since that's the publishing-standard term this maps from.
     */
    public function up(): void
    {
        Schema::create('queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('stage');
            $table->string('title');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->index(['submission_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queries');
    }
};
