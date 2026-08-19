<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ordered, with ORCID + ROR (CLAUDE.md §3).
     */
    public function up(): void
    {
        Schema::create('contributors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->string('given_name');
            $table->string('family_name');
            $table->string('email')->nullable();
            $table->string('orcid', 19)->nullable();
            $table->string('affiliation')->nullable();
            $table->string('ror_id')->nullable();
            $table->string('role')->default('author');
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributors');
    }
};
