<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reader-facing search over published articles (CLAUDE.md §7, §2 — "start with Postgres FTS").
     *
     * A generated column rather than a trigger: Postgres keeps it in step with title and abstract
     * automatically, so no write path can forget to reindex and no backfill command is needed.
     *
     * jsonb_path_query_array(title, '$.*') rather than title::text — the latter includes the
     * language keys, so every article would match a search for "en". Both are immutable, which is
     * what a generated column requires.
     *
     * The 'simple' configuration deliberately does no stemming. It is the only one that treats
     * English and Khmer alike; an English-specific config would stem English and mangle Khmer.
     * Khmer writes without spaces, so FTS cannot split it into words — the search also runs an
     * ILIKE substring match, which is what actually makes Khmer queries work.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            // SQLite (tests) has no tsvector. The query layer falls back to LIKE there, so the
            // absence of this column is handled rather than fatal.
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE publications
            ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                to_tsvector(
                    'simple',
                    coalesce(jsonb_path_query_array(title, '$.*')::text, '') || ' ' ||
                    coalesce(jsonb_path_query_array(abstract, '$.*')::text, '') || ' ' ||
                    coalesce(doi, '')
                )
            ) STORED
        SQL);

        DB::statement('CREATE INDEX publications_search_vector_idx ON publications USING GIN (search_vector)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS publications_search_vector_idx');

        Schema::table('publications', function ($table) {
            $table->dropColumn('search_vector');
        });
    }
};
