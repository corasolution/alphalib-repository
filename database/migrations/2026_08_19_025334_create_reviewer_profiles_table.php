<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reviewer profile: interests, availability toggle, "unavailable until" date (CLAUDE.md
     * §11.4). Per-journal, not global — a reviewer's declared interests and availability for one
     * journal don't necessarily apply to another they also review for.
     */
    public function up(): void
    {
        Schema::create('reviewer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('interests')->nullable();
            $table->boolean('is_available')->default(true);
            $table->date('unavailable_until')->nullable();
            $table->timestamps();

            $table->unique(['journal_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviewer_profiles');
    }
};
