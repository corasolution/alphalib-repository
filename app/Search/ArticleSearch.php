<?php

namespace Alpha\Journal\Search;

use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Scopes\JournalScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reader-facing search across published articles (CLAUDE.md §7).
 *
 * Two matching strategies run together, because neither alone covers both languages this platform
 * serves. Postgres full-text search gives ranking and word-boundary matching, which is what makes
 * English results useful. But Khmer is written without spaces, so FTS sees a whole phrase as one
 * token and only an exact full-phrase query would hit — so an ILIKE substring match runs
 * alongside it. A query matches if either does; ranking comes from FTS where available.
 *
 * Everything is scoped through Publication::publishedIn(), the same double gate the article page
 * uses: the publication row and the submission's stage must BOTH say published. Search must never
 * be the hole through which an unpublished manuscript becomes discoverable.
 */
class ArticleSearch
{
    public const SORTS = ['relevance', 'newest', 'oldest', 'title'];

    public const PER_PAGE = 10;

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function run(array $criteria): LengthAwarePaginator
    {
        $query = Publication::query()
            ->publishedInAnyJournal()
            ->with([
                'contributors',
                'galleys',
                // Submission, Issue and Section all carry JournalScope through BelongsToJournal,
                // and it denies by default when no journal is in context — which is every public
                // search request. An eager load is a query like any other, so without these opt-
                // outs the submission resolves to null and the result loses the journal whose
                // path builds its own link. Shipped once as "/journal/undefined/article/{id}".
                // (Journal itself is deliberately unscoped, so it needs no opt-out here.)
                'submission' => fn ($q) => $q->withoutGlobalScope(JournalScope::class),
                'submission.journal',
                'submission.issue' => fn ($q) => $q->withoutGlobalScope(JournalScope::class),
                'submission.section' => fn ($q) => $q->withoutGlobalScope(JournalScope::class),
            ]);

        $terms = trim((string) ($criteria['q'] ?? ''));

        if ($terms !== '') {
            $this->applyFreeText($query, $terms);
        }

        // Field-specific narrowing. Each is an AND, so an advanced search gets more precise as
        // fields are filled rather than returning the union of everything asked for.
        if (filled($criteria['title'] ?? null)) {
            $query->whereRaw($this->jsonTextExpression('title').' '.$this->like().' ?', ['%'.$criteria['title'].'%']);
        }

        if (filled($criteria['abstract'] ?? null)) {
            $query->whereRaw($this->jsonTextExpression('abstract').' '.$this->like().' ?', ['%'.$criteria['abstract'].'%']);
        }

        if (filled($criteria['author'] ?? null)) {
            $author = '%'.$criteria['author'].'%';

            $query->whereHas('contributors', fn (Builder $q) => $q
                ->whereRaw("(given_name || ' ' || family_name) ".$this->like().' ?', [$author])
                ->orWhereRaw("(family_name || ' ' || given_name) ".$this->like().' ?', [$author])
                ->orWhere('affiliation', $this->like(), $author)
                ->orWhere('orcid', $this->like(), $author));
        }

        if (filled($criteria['doi'] ?? null)) {
            $query->where('doi', $this->like(), '%'.$criteria['doi'].'%');
        }

        // withoutGlobalScope on every submission/journal constraint: JournalScope denies by
        // default when no journal is in context, and search runs on public requests that have
        // none — so a scoped whereHas quietly matches nothing. Invisible under `artisan test`,
        // where JournalScope bypasses entirely (see bootstrap/app.php).
        if (filled($criteria['journal'] ?? null)) {
            $query->whereHas('submission', fn (Builder $q) => $q
                ->withoutGlobalScope(JournalScope::class)
                ->whereHas('journal', fn (Builder $j) => $j
                    ->withoutGlobalScope(JournalScope::class)
                    ->where('path', $criteria['journal'])));
        }

        if (filled($criteria['section'] ?? null)) {
            $query->whereHas('submission', fn (Builder $q) => $q
                ->withoutGlobalScope(JournalScope::class)
                ->where('section_id', $criteria['section']));
        }

        // Year bounds read from the publication date, not the issue year: an article is findable
        // by when it actually became available.
        if (filled($criteria['year_from'] ?? null)) {
            $query->whereYear('published_at', '>=', $criteria['year_from']);
        }

        if (filled($criteria['year_to'] ?? null)) {
            $query->whereYear('published_at', '<=', $criteria['year_to']);
        }

        $this->applySort($query, $criteria['sort'] ?? 'relevance', $terms);

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * FTS for ranking and word boundaries, ILIKE for everything FTS cannot tokenise.
     *
     * websearch_to_tsquery rather than plainto_tsquery: it understands quoted phrases, OR and -
     * exclusion, which is what a reader typing into a search box already expects from every other
     * search box they use.
     */
    private function applyFreeText(Builder $query, string $terms): void
    {
        $like = '%'.$terms.'%';

        if (! $this->supportsFullText()) {
            // SQLite, in tests. LIKE alone still finds the article; only ranking is lost.
            $query->where(fn (Builder $q) => $q
                ->whereRaw($this->jsonTextExpression('title').' LIKE ?', [$like])
                ->orWhereRaw($this->jsonTextExpression('abstract').' LIKE ?', [$like])
                ->orWhere('doi', 'LIKE', $like)
                ->orWhereHas('contributors', fn (Builder $c) => $c
                    ->whereRaw("(given_name || ' ' || family_name) LIKE ?", [$like])));

            return;
        }

        $query->where(fn (Builder $q) => $q
            ->whereRaw("search_vector @@ websearch_to_tsquery('simple', ?)", [$terms])
            ->orWhereRaw($this->jsonTextExpression('title').' ILIKE ?', [$like])
            ->orWhereRaw($this->jsonTextExpression('abstract').' ILIKE ?', [$like])
            ->orWhereHas('contributors', fn (Builder $c) => $c
                ->whereRaw("(given_name || ' ' || family_name) ILIKE ?", [$like])
                ->orWhereRaw("(family_name || ' ' || given_name) ILIKE ?", [$like])));
    }

    private function applySort(Builder $query, string $sort, string $terms): void
    {
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'relevance';

        // Relevance is only meaningful with a query behind it; without one it means "newest".
        if ($sort === 'relevance' && $terms !== '' && $this->supportsFullText()) {
            $query->orderByRaw("ts_rank(search_vector, websearch_to_tsquery('simple', ?)) DESC", [$terms])
                ->orderByDesc('published_at');

            return;
        }

        match ($sort) {
            'oldest' => $query->orderBy('published_at'),
            'title' => $query->orderByRaw($this->jsonTextExpression('title').' ASC'),
            default => $query->orderByDesc('published_at'),
        };
    }

    /**
     * The multilingual values as one searchable string, with the language keys left out — an
     * article should not match a search for "en".
     */
    private function jsonTextExpression(string $column): string
    {
        return $this->supportsFullText()
            ? "jsonb_path_query_array({$column}, '$.*')::text"
            : "CAST({$column} AS TEXT)";
    }

    /** Postgres spells case-insensitive matching ILIKE; SQLite's LIKE is already insensitive. */
    private function like(): string
    {
        return $this->supportsFullText() ? 'ILIKE' : 'LIKE';
    }

    private function supportsFullText(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
}
