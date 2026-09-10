<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Support\JournalContext;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public, unauthenticated homepage for a single journal.
 *
 * Two doctrines govern what lands here. First, real data or nothing — marketing-adjacent copy is
 * free-form journal_settings a manager may set, and a block simply doesn't render when unset;
 * nothing is defaulted or invented. Second, the page is a *listing*: the current issue's contents
 * and an archive of past issues, wearing the same site chrome as the index page, rather than a
 * brochure that buries the articles below the fold.
 */
class PublicJournalController extends Controller
{
    private const TEXT_SETTINGS = ['tagline', 'about'];

    public function show(string $journal): Response
    {
        $currentJournal = app(JournalContext::class)->requireJournal();
        $site = Site::firstOrFail();

        // The reader's chosen language first, then the journal's own, then the site fallback.
        // Previously this was the journal's primary locale only, so a Khmer reader was served
        // English titles even where a Khmer translation existed on the record.
        $chain = array_values(array_unique(array_filter([
            app()->getLocale(),
            $currentJournal->primary_locale,
            $site->primary_locale,
            config('app.fallback_locale'),
        ])));
        $locale = $chain[0];

        // Newest first: the head of this list is the current issue, the tail is the archive.
        $issues = Issue::where('journal_id', $currentJournal->id)
            ->where('is_published', true)
            ->orderByDesc('publish_at')
            ->get();

        $current = $issues->first();

        return Inertia::render('public/journal-home', [
            // Site chrome, so this page wears the same header and footer as every other public
            // page. socialLinks comes from the shared helper rather than being rebuilt here —
            // the CMS page's footer lost its icons precisely by rebuilding it and forgetting.
            'site' => [
                'title' => $site->title,
                'logoUrl' => $site->logoUrl(),
                'footerText' => $site->settings['footer_text'] ?? null,
            ],
            'menuItems' => $site->settings['menu_items'] ?? [],
            'socialLinks' => $site->socialLinks(),

            'journal' => [
                'path' => $currentJournal->path,
                'title' => $currentJournal->title->get(...$chain) ?? $currentJournal->path,
            ],
            'content' => $this->textSettings($currentJournal, $locale),

            'currentIssue' => $current ? [
                'identification' => $current->identification(),
                'title' => $current->title?->get(...$chain),
                'articles' => $this->publishedArticles($currentJournal, $current, $chain),
            ] : null,

            // Everything older than the current issue. Counted, not loaded — the archive shows
            // how much is in each issue without pulling every publication on the site.
            'archive' => $issues->skip(1)->map(fn (Issue $issue) => [
                'id' => $issue->id,
                'identification' => $issue->identification(),
                'title' => $issue->title?->get(...$chain),
                'articleCount' => Publication::publishedIn($currentJournal, $issue)->count(),
            ])->values(),
        ]);
    }

    /** @return array<string, string> only the keys that are actually set */
    private function textSettings(Journal $journal, string $locale): array
    {
        $rows = $journal->settings()
            ->whereIn('name', self::TEXT_SETTINGS)
            ->where(fn ($q) => $q->where('locale', $locale)->orWhere('locale', ''))
            ->get()
            ->sortByDesc(fn ($row) => $row->locale === $locale)
            ->unique('name')
            ->keyBy('name');

        $content = [];

        foreach (self::TEXT_SETTINGS as $key) {
            if (isset($rows[$key]) && filled($rows[$key]->value)) {
                $content[$key] = $rows[$key]->value;
            }
        }

        return $content;
    }

    /**
     * @return array<int, array{id: int, title: ?string, authors: string}>
     *
     * @see Publication::scopePublishedIn() for why status alone is not enough.
     */
    /** @param list<string> $chain */
    private function publishedArticles(Journal $journal, Issue $issue, array $chain): array
    {
        return Publication::publishedIn($journal, $issue)
            ->with('contributors')
            ->orderBy('published_at')
            ->get()
            ->map(fn (Publication $publication) => [
                'id' => $publication->id,
                'title' => $publication->title->get(...$chain),
                'authors' => $publication->contributors
                    ->map(fn ($c) => trim("{$c->given_name} {$c->family_name}"))
                    ->implode(', '),
            ])
            ->values()
            ->all();
    }
}
