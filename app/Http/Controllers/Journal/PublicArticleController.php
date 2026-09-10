<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\PublicationGalley;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Support\JournalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The public article landing page (CLAUDE.md §7).
 *
 * Publishing previously produced a title in a list and nothing else — no page to open, no file to
 * download. This is what makes a published article actually readable, and what an indexer
 * crawls.
 *
 * Every lookup goes through Publication::publishedIn(), the double gate: the publication row and
 * the submission's own stage must BOTH say published. Filtering on status alone would serve a
 * withdrawn article at a stable URL, which is the worst version of this mistake.
 */
class PublicArticleController extends Controller
{
    public function show(string $journal, int $publication): Response
    {
        $currentJournal = app(JournalContext::class)->requireJournal();
        $site = Site::firstOrFail();

        $article = $this->findPublished($currentJournal, $publication);

        $chain = array_values(array_unique(array_filter([
            app()->getLocale(),
            $currentJournal->primary_locale,
            $site->primary_locale,
            config('app.fallback_locale'),
        ])));

        // A dedicated root view carrying the Google Scholar citation_* meta tags. Inertia SSR is
        // not configured (§7 asks for it), so the tags are rendered server-side here instead —
        // Scholar reads the initial HTML and does not run our JavaScript.
        Inertia::setRootView('article');

        return Inertia::render('public/article', [
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
            'article' => $this->payload($article, $chain),
        ]);
    }

    /**
     * Streams a galley through the app rather than exposing the storage URL.
     *
     * Redirecting to a temporary object-store URL would work, but it takes the download outside
     * anything this app can see — and §11.7's usage statistics have to count article downloads.
     * Going through here keeps that possible.
     */
    public function galley(string $journal, int $publication, PublicationGalley $publicationGalley): SymfonyResponse|RedirectResponse|HttpResponse
    {
        $currentJournal = app(JournalContext::class)->requireJournal();
        $article = $this->findPublished($currentJournal, $publication);

        abort_unless($publicationGalley->publication_id === $article->id, 404);

        return redirect()->away($publicationGalley->url());
    }

    /**
     * Citation export (§7). Formats a reference manager can import directly, rather than leaving
     * a reader to retype the metadata from the page.
     */
    public function citation(string $journal, int $publication, string $format): HttpResponse
    {
        $currentJournal = app(JournalContext::class)->requireJournal();
        $article = $this->findPublished($currentJournal, $publication);
        $locale = $currentJournal->primary_locale;

        $title = $article->title->get($locale, 'en') ?? "Article #{$article->id}";
        $journalTitle = $currentJournal->title->get($locale, 'en') ?? $currentJournal->path;
        $year = $article->published_at?->format('Y') ?? '';
        $authors = $article->contributors->map(fn ($c) => trim("{$c->family_name}, {$c->given_name}"))->filter();

        [$body, $mime, $extension] = match ($format) {
            'bibtex' => [$this->bibtex($article, $title, $journalTitle, $year, $authors), 'application/x-bibtex', 'bib'],
            'ris' => [$this->ris($article, $title, $journalTitle, $year, $authors), 'application/x-research-info-systems', 'ris'],
            'csl' => [$this->cslJson($article, $title, $journalTitle, $authors), 'application/vnd.citationstyles.csl+json', 'json'],
            default => abort(404),
        };

        return response($body, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="article-'.$article->id.'.'.$extension.'"',
        ]);
    }

    /** @return Publication the published article, or a 404 */
    private function findPublished($currentJournal, int $publication): Publication
    {
        return Publication::publishedIn($currentJournal)
            ->with(['contributors', 'galleys', 'submission.issue', 'submission.section'])
            ->findOrFail($publication);
    }

    /**
     * @param  list<string>  $chain
     * @return array<string, mixed>
     */
    private function payload(Publication $article, array $chain): array
    {
        $submission = $article->submission;

        return [
            'id' => $article->id,
            'title' => $article->title->get(...$chain),
            'abstract' => $article->abstract?->get(...$chain),
            'doi' => $article->doi,
            'version' => $article->version,
            'publishedAt' => $article->published_at?->toIso8601String(),
            'section' => $submission?->section?->title?->get(...$chain),
            'issue' => $submission?->issue ? [
                'identification' => $submission->issue->identification(),
                'title' => $submission->issue->title?->get(...$chain),
            ] : null,
            'authors' => $article->contributors->map(fn ($c) => [
                'name' => trim("{$c->given_name} {$c->family_name}"),
                'affiliation' => $c->affiliation,
                'orcid' => $c->orcid,
            ])->values(),
            'galleys' => $article->galleys->map(fn (PublicationGalley $galley) => [
                'id' => $galley->id,
                'label' => $galley->label,
                'size' => $galley->humanSize(),
            ])->values(),
        ];
    }

    private function bibtex(Publication $a, string $title, string $journal, string $year, $authors): string
    {
        $key = 'article'.$a->id;
        $lines = [
            "@article{{$key},",
            '  title = {'.$title.'},',
            '  author = {'.$authors->implode(' and ').'},',
            '  journal = {'.$journal.'},',
        ];

        if ($year !== '') {
            $lines[] = '  year = {'.$year.'},';
        }

        if ($a->doi) {
            $lines[] = '  doi = {'.$a->doi.'},';
        }

        $lines[] = '}';

        return implode("\n", $lines)."\n";
    }

    private function ris(Publication $a, string $title, string $journal, string $year, $authors): string
    {
        $lines = ['TY  - JOUR'];

        foreach ($authors as $author) {
            $lines[] = 'AU  - '.$author;
        }

        $lines[] = 'TI  - '.$title;
        $lines[] = 'JO  - '.$journal;

        if ($year !== '') {
            $lines[] = 'PY  - '.$year;
        }

        if ($a->doi) {
            $lines[] = 'DO  - '.$a->doi;
        }

        $lines[] = 'ER  - ';

        return implode("\r\n", $lines)."\r\n";
    }

    private function cslJson(Publication $a, string $title, string $journal, $authors): string
    {
        return json_encode([
            'id' => 'article'.$a->id,
            'type' => 'article-journal',
            'title' => $title,
            'container-title' => $journal,
            'DOI' => $a->doi,
            'author' => $a->contributors->map(fn ($c) => [
                'family' => $c->family_name,
                'given' => $c->given_name,
            ])->values(),
            'issued' => $a->published_at
                ? ['date-parts' => [[(int) $a->published_at->format('Y'), (int) $a->published_at->format('n'), (int) $a->published_at->format('j')]]]
                : null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
