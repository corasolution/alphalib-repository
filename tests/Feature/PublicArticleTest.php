<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Contributor;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\PublicationGalley;
use Alpha\Journal\Models\Section;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Fixtures\ManuscriptFixtures;
use Tests\TestCase;

/**
 * The public article page — the last step of "submit to publish", and until now the only step
 * with no test at all.
 *
 * The load-bearing case is the double gate: Publication::publishedIn() requires the publication
 * row AND the submission's own stage to both say published. Filtering on either alone would serve
 * a withdrawn or still-in-review article at a stable, indexable URL.
 */
class PublicArticleTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private Submission $submission;

    private Publication $article;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create([
            'site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal'], 'is_enabled' => true,
        ]);
        $section = Section::create(['journal_id' => $this->journal->id, 'title' => ['en' => 'Articles'], 'sort_order' => 0]);
        $issue = Issue::create([
            'journal_id' => $this->journal->id, 'volume' => 2, 'number' => 1, 'year' => 2026,
            'is_published' => true, 'publish_at' => now(),
        ]);

        $author = User::factory()->create(['name' => 'Ana Author']);

        $this->submission = Submission::create([
            'journal_id' => $this->journal->id,
            'section_id' => $section->id,
            'issue_id' => $issue->id,
            'submitted_by' => $author->id,
            'stage' => SubmissionStage::Published->value,
            'submitted_at' => now(),
        ]);

        $this->article = Publication::create([
            'submission_id' => $this->submission->id,
            'version' => 1,
            'title' => ['en' => 'Seagrass Recovery in Kampot Bay'],
            'abstract' => ['en' => 'We tracked eighteen meadows over two years.'],
            'status' => 'published',
            'published_at' => now(),
            'doi' => '10.1234/demo.1',
        ]);

        $this->submission->update(['current_publication_id' => $this->article->id]);

        Contributor::create([
            'publication_id' => $this->article->id,
            'given_name' => 'Ana', 'family_name' => 'Author',
            'email' => $author->email, 'role' => 'author', 'sequence' => 0,
        ]);

        $this->dir = sys_get_temp_dir().'/alpha-article-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function attachGalley(string $label = 'PDF'): PublicationGalley
    {
        $path = ManuscriptFixtures::pdf($this->dir.'/galley.pdf', 'Seagrass Recovery in Kampot Bay');

        return PublicationGalley::storeFor($this->article, new UploadedFile($path, 'galley.pdf', null, null, true), $label);
    }

    public function test_a_reader_who_is_not_logged_in_can_read_a_published_article(): void
    {
        $this->attachGalley();

        $this->get("/journal/demo/article/{$this->article->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public/article')
                ->where('article.title', 'Seagrass Recovery in Kampot Bay')
                ->where('article.doi', '10.1234/demo.1')
                ->where('article.authors.0.name', 'Ana Author')
                ->where('article.issue.identification', 'Vol. 2, No. 1, 2026')
                ->has('article.galleys', 1)
                ->where('article.galleys.0.label', 'PDF'));
    }

    /**
     * Google Scholar reads the initial HTML and does not run our JavaScript, which is why the
     * controller swaps in a dedicated root view. If that ever regresses the article silently
     * stops being indexable — nothing else in the app would notice.
     */
    public function test_the_page_carries_scholar_citation_meta_tags(): void
    {
        $html = $this->get("/journal/demo/article/{$this->article->id}")->getContent();

        $this->assertStringContainsString('citation_title', $html);
        $this->assertStringContainsString('Seagrass Recovery in Kampot Bay', $html);
        $this->assertStringContainsString('citation_journal_title', $html);
    }

    public function test_a_publication_whose_submission_is_not_published_is_not_served(): void
    {
        // The double gate. The publication row still says published; the submission does not.
        $this->submission->update(['stage' => SubmissionStage::Review->value]);

        $this->get("/journal/demo/article/{$this->article->id}")->assertNotFound();
    }

    public function test_a_draft_publication_is_not_served(): void
    {
        $this->article->update(['status' => 'draft']);

        $this->get("/journal/demo/article/{$this->article->id}")->assertNotFound();
    }

    public function test_an_article_from_another_journal_is_not_served_here(): void
    {
        $site = Site::first();
        Journal::create(['site_id' => $site->id, 'path' => 'other', 'title' => ['en' => 'Other'], 'is_enabled' => true]);

        $this->get("/journal/other/article/{$this->article->id}")->assertNotFound();
    }

    public function test_a_galley_can_be_downloaded(): void
    {
        $galley = $this->attachGalley();

        $response = $this->get("/journal/demo/article/{$this->article->id}/galley/{$galley->id}");

        // The local disk stands in for R2 in dev and test, and signs a temporary URL rather than
        // streaming bytes — so a redirect is the pass condition, not a 200.
        $this->assertContains($response->getStatusCode(), [200, 302], 'A galley must be reachable.');
    }

    public function test_a_galley_belonging_to_another_article_is_not_served_under_this_one(): void
    {
        $other = Publication::create([
            'submission_id' => $this->submission->id,
            'version' => 2,
            'title' => ['en' => 'A Different Version'],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $path = ManuscriptFixtures::pdf($this->dir.'/other.pdf', 'Other');
        $foreign = PublicationGalley::storeFor($other, new UploadedFile($path, 'other.pdf', null, null, true), 'PDF');

        $this->get("/journal/demo/article/{$this->article->id}/galley/{$foreign->id}")->assertNotFound();
    }

    public function test_every_citation_format_exports(): void
    {
        $expectations = [
            'bibtex' => ['application/x-bibtex', '@article{article'.$this->article->id],
            'ris' => ['application/x-research-info-systems', 'TY  - JOUR'],
            'csl' => ['application/vnd.citationstyles.csl+json', '"type": "article-journal"'],
        ];

        foreach ($expectations as $format => [$mime, $needle]) {
            $response = $this->get("/journal/demo/article/{$this->article->id}/cite/{$format}");

            $response->assertOk();
            $this->assertStringContainsString($mime, $response->headers->get('Content-Type'), "{$format} sent the wrong type.");
            $this->assertStringContainsString($needle, $response->getContent(), "{$format} body looks wrong.");
            $this->assertStringContainsString('Seagrass Recovery in Kampot Bay', $response->getContent());
            $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        }
    }

    public function test_an_unknown_citation_format_is_not_invented(): void
    {
        $this->get("/journal/demo/article/{$this->article->id}/cite/endnote")->assertNotFound();
    }

    public function test_citations_for_an_unpublished_article_are_not_served(): void
    {
        // The metadata is as confidential as the page itself — the gate has to hold on every
        // route, not only the one a reader is likely to try.
        $this->submission->update(['stage' => SubmissionStage::Review->value]);

        $this->get("/journal/demo/article/{$this->article->id}/cite/bibtex")->assertNotFound();
    }
}
