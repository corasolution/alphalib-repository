<?php

namespace Alpha\Journal\Http\Controllers;

use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Support\JournalListing;
use Alpha\Journal\Support\SiteTheme;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The site-wide homepage — real content only, same doctrine as PublicJournalController: nothing
 * fabricated, everything either a real Site/Journal field or an explicitly-set settings value.
 * `Site.redirect_journal_id` (already in the schema, unused until now) lets a single-journal
 * install skip this page entirely and send '/' straight to its one journal.
 */
class SiteController extends Controller
{
    public function home(): Response|RedirectResponse
    {
        $site = Site::firstOrFail();

        if ($site->redirect_journal_id) {
            $journal = Journal::find($site->redirect_journal_id);

            if ($journal && $journal->is_enabled) {
                return redirect()->route('journal.public.show', ['journal' => $journal->path]);
            }
        }

        // A single-journal library can show that journal's issues right on the homepage. Unlike
        // the redirect above this keeps the visitor on '/', wearing the site's own design.
        $featured = null;
        $embedId = $site->settings['embed_journal_id'] ?? null;
        $embedded = $embedId ? Journal::find($embedId) : null;

        if ($embedded && $embedded->is_enabled) {
            $chain = array_values(array_unique(array_filter([
                app()->getLocale(),
                $embedded->primary_locale,
                $site->primary_locale,
                config('app.fallback_locale'),
            ])));

            $featured = [
                'journal' => [
                    'path' => $embedded->path,
                    'title' => $embedded->title->get(...$chain) ?? $embedded->path,
                ],
            ] + JournalListing::for($embedded, $chain);
        }

        return Inertia::render('site/home', [
            // Which layout renders. Resolved server-side so the admin preview and the stored
            // choice go through one place, and an unknown key falls back rather than blanking.
            'template' => app(SiteTheme::class)->template(request()),
            'site' => [
                'title' => $site->displayTitle(),
                'tagline' => $site->displayTagline(),
                'about' => $site->displayAbout(),
                'footerText' => $site->displayFooterText(),
                'logoUrl' => $site->logoUrl(),
            ],
            'menuItems' => $site->displayMenuItems(),
            'socialLinks' => $site->socialLinks(),
            'featuredJournal' => $featured,
            'highlights' => $site->highlights(),
            'heroSlides' => $site->heroSlides->map(fn ($slide) => [
                'id' => $slide->id,
                'url' => $slide->url(),
                'caption' => $slide->caption,
            ]),
            'journals' => Journal::where('is_enabled', true)
                ->orderBy('title->'.$site->primary_locale)
                ->get()
                ->map(fn ($journal) => [
                    'path' => $journal->path,
                    'title' => $journal->title->get(app()->getLocale(), $site->primary_locale, 'en') ?? $journal->path,
                ]),
        ]);
    }

    /**
     * The full journals index behind the "Journals" header menu link. Dynamic data, so it's a
     * real route rather than a SitePage — a site admin can't hand-maintain a list that changes
     * whenever a journal is added or an article is published.
     */
    public function journals(): Response
    {
        $site = Site::firstOrFail();
        // Reader's language first; journalTagline() and the titles below fall back through it.
        $locale = app()->getLocale();

        return Inertia::render('site/journals', [
            'site' => [
                'title' => $site->displayTitle(),
                'logoUrl' => $site->logoUrl(),
                'footerText' => $site->displayFooterText(),
            ],
            'menuItems' => $site->displayMenuItems(),
            'socialLinks' => $site->socialLinks(),
            'journals' => Journal::where('is_enabled', true)
                ->orderBy('title->'.$site->primary_locale)
                ->get()
                ->map(fn ($journal) => [
                    'path' => $journal->path,
                    'title' => $journal->title->get($locale, $site->primary_locale, 'en') ?? $journal->path,
                    'description' => $this->journalTagline($journal, $locale),
                    'articleCount' => $this->publishedArticleCount($journal),
                ]),
        ]);
    }

    private function journalTagline(Journal $journal, string $locale): ?string
    {
        $row = $journal->settings()
            ->where('name', 'tagline')
            ->where(fn ($q) => $q->where('locale', $locale)->orWhere('locale', ''))
            ->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$locale])
            ->first();

        return filled($row?->value) ? $row->value : null;
    }

    /** @see Publication::scopePublishedIn() — status alone isn't proof of publication. */
    private function publishedArticleCount(Journal $journal): int
    {
        return Publication::publishedIn($journal)->count();
    }
}
