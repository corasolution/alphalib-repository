<?php

namespace Alpha\Journal\Http\Controllers;

use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Section;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Search\ArticleSearch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reader-facing article search (CLAUDE.md §7).
 *
 * Public and unauthenticated by design — this is how somebody finds the research, and requiring
 * an account to look would defeat the point of publishing open access.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, ArticleSearch $search): Response
    {
        $site = Site::firstOrFail();
        $locale = app()->getLocale();

        $criteria = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'title' => ['nullable', 'string', 'max:200'],
            'author' => ['nullable', 'string', 'max:200'],
            'abstract' => ['nullable', 'string', 'max:200'],
            'doi' => ['nullable', 'string', 'max:200'],
            'journal' => ['nullable', 'string', 'max:255'],
            'section' => ['nullable', 'integer'],
            'year_from' => ['nullable', 'integer', 'min:1000', 'max:2999'],
            'year_to' => ['nullable', 'integer', 'min:1000', 'max:2999'],
            'sort' => ['nullable', Rule::in(ArticleSearch::SORTS)],
        ]);

        // Nothing asked for yet — show the form rather than every article on the site, which
        // would look like a result set nobody requested.
        $hasCriteria = collect($criteria)->except('sort')->filter(fn ($v) => filled($v))->isNotEmpty();

        $results = $hasCriteria ? $search->run($criteria) : null;

        $journals = Journal::withoutGlobalScopes()
            ->where('is_enabled', true)
            ->orderBy('title->'.$site->primary_locale)
            ->get()
            ->map(fn (Journal $journal) => [
                'path' => $journal->path,
                'title' => $journal->title->get($locale, $site->primary_locale, 'en') ?? $journal->path,
            ]);

        return Inertia::render('public/search', [
            'site' => [
                'title' => $site->title,
                'logoUrl' => $site->logoUrl(),
                'footerText' => $site->settings['footer_text'] ?? null,
            ],
            'menuItems' => $site->settings['menu_items'] ?? [],
            'socialLinks' => $site->socialLinks(),
            'criteria' => $criteria + ['sort' => $criteria['sort'] ?? 'relevance'],
            'journals' => $journals,
            // Sections of the journal being filtered on, if one is. Listing every section of every
            // journal would be a long list of names that mean nothing without their journal.
            'sections' => filled($criteria['journal'] ?? null)
                ? $this->sectionsFor($criteria['journal'], $locale, $site)
                : [],
            'results' => $results?->through(fn (Publication $article) => $this->row($article, $locale, $site)),
            'searched' => $hasCriteria,
        ]);
    }

    /** @return array<string, mixed> */
    private function row(Publication $article, string $locale, Site $site): array
    {
        $submission = $article->submission;
        $journal = $submission?->journal;

        return [
            'id' => $article->id,
            'title' => $article->title?->get($locale, $site->primary_locale, 'en'),
            'abstract' => $article->abstract?->get($locale, $site->primary_locale, 'en'),
            'doi' => $article->doi,
            'publishedAt' => $article->published_at?->toIso8601String(),
            'authors' => $article->contributors
                ->map(fn ($c) => trim("{$c->given_name} {$c->family_name}"))
                ->filter()
                ->values(),
            'journal' => $journal ? [
                'path' => $journal->path,
                'title' => $journal->title->get($locale, $site->primary_locale, 'en') ?? $journal->path,
            ] : null,
            'issue' => $submission?->issue?->identification(),
            'section' => $submission?->section?->title?->get($locale, $site->primary_locale, 'en'),
            'hasFile' => $article->galleys->isNotEmpty(),
        ];
    }

    /** @return array<int, array{id: int, title: string}> */
    private function sectionsFor(string $journalPath, string $locale, Site $site): array
    {
        $journal = Journal::withoutGlobalScopes()->where('path', $journalPath)->where('is_enabled', true)->first();

        if (! $journal) {
            return [];
        }

        return Section::withoutGlobalScopes()
            ->where('journal_id', $journal->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Section $section) => [
                'id' => $section->id,
                'title' => $section->title?->get($locale, $site->primary_locale, 'en') ?? "Section #{$section->id}",
            ])
            ->all();
    }
}
