<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "Indexed by" and "Tools" strips on the public site.
     *
     * A table rather than a jsonb settings key because each entry carries an uploaded image, and
     * files need the same disk+path+checksum treatment every other upload gets (see hero_slides,
     * which this mirrors).
     *
     * `group` keeps the two strips in one table: they differ only in heading and meaning, and
     * splitting them would duplicate the whole model, controller and screen for no gain.
     */
    public function up(): void
    {
        Schema::create('site_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            // 'indexed_by' or 'tools'. A string, not an enum, so adding a third strip later is a
            // code change rather than a migration on a live database.
            $table->string('group', 32);
            $table->string('label');
            $table->string('url')->nullable();
            // Nullable: a badge with no image renders as a clean text chip, which is a legitimate
            // way to ship before brand assets have been gathered.
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['site_id', 'group', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_badges');
    }
};
