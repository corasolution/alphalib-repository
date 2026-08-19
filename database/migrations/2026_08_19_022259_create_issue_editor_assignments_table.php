<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Closes the Guest Editor gap noted in SubmissionWorkflow: "assigned issue only" (CLAUDE.md
     * §4) needs a record of which issues a Guest Editor actually owns — this is that record.
     */
    public function up(): void
    {
        Schema::create('issue_editor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['issue_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_editor_assignments');
    }
};
