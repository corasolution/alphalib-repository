<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The public editorial board: named groups (Advisory Council, Editors-in-Chief, …) each
     * holding people.
     *
     * Real tables rather than a Site.settings key, for the reason HeroSlideController already
     * spells out: these are individually-addressable resources — upload one photo, delete one
     * person, reorder two — not the fixed set of single-value fields a settings form saves as a
     * batch. Board members are also deliberately NOT users: almost none of them will ever hold an
     * account on this installation, so tying them to journal_user_roles would mean inventing
     * accounts for people purely so they can be listed on a page.
     */
    public function up(): void
    {
        Schema::create('board_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['site_id', 'sort_order']);
        });

        Schema::create('board_members', function (Blueprint $table) {
            $table->id();

            // Deleting a group takes its people with it — a member has no meaning outside one,
            // and orphan rows would silently accumulate with no screen that can reach them.
            $table->foreignId('board_group_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('role')->nullable();
            $table->string('affiliation')->nullable();

            // Photo is optional: the card falls back to an initials avatar, so the page is
            // presentable before anyone has collected 18 portraits.
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['board_group_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_members');
        Schema::dropIfExists('board_groups');
    }
};
