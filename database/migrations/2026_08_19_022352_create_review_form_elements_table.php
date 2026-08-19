<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_form_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_form_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->jsonb('label');
            $table->jsonb('help_text')->nullable();
            $table->boolean('is_required')->default(true);
            $table->jsonb('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_form_elements');
    }
};
