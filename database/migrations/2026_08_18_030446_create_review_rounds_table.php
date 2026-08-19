<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * REVIEW supports multiple rounds; each round has its own file set and assignments (§3).
     */
    public function up(): void
    {
        Schema::create('review_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->string('status')->default('open');
            $table->timestamps();

            $table->unique(['submission_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_rounds');
    }
};
