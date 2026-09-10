<?php

namespace Tests\Feature;

use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\PublicationGalley;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The demonstration data has to demonstrate a working journal.
 *
 * It previously seeded published articles with no galley, so the article page told every visitor
 * "No file has been published for this article yet" — a broken journal rather than an example of
 * one. Nothing caught it because nothing ran the seeder.
 */
class SiteContentSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');

        // The whole thing, not just SiteContentSeeder: it needs the Site and the demo Journal that
        // DatabaseSeeder creates, and running the real entry point is what `php artisan db:seed`
        // actually does.
        $this->seed();
    }

    public function test_it_seeds_published_articles(): void
    {
        $this->assertGreaterThan(0, Publication::where('status', 'published')->count());
    }

    public function test_every_published_article_has_something_to_download(): void
    {
        $published = Publication::where('status', 'published')->get();

        foreach ($published as $article) {
            $this->assertTrue(
                PublicationGalley::where('publication_id', $article->id)->exists(),
                "Published article #{$article->id} has no galley, so a reader has nothing to open.",
            );
        }
    }

    public function test_the_seeded_galley_is_a_real_pdf_that_was_actually_stored(): void
    {
        $galley = PublicationGalley::firstOrFail();

        $this->assertSame('application/pdf', $galley->mime_type);
        $this->assertNotNull($galley->sha256);
        Storage::disk('r2')->assertExists($galley->path);

        $bytes = Storage::disk('r2')->get($galley->path);

        // A file that is not openable is no better than no file at all.
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringContainsString('%%EOF', $bytes);
        $this->assertStringContainsString('xref', $bytes);
    }

    public function test_the_galley_names_its_own_article_and_says_it_is_sample_content(): void
    {
        $article = Publication::where('status', 'published')->firstOrFail();
        $galley = PublicationGalley::where('publication_id', $article->id)->firstOrFail();

        $text = Storage::disk('r2')->get($galley->path);
        $title = $article->title->get('en');

        // Long titles are wrapped across lines, so check a leading fragment rather than the whole
        // string — asserting the untruncated title would fail for a reason nobody cares about.
        $this->assertStringContainsString(substr($title, 0, 25), $text);

        // Seed data that reads like real findings is worse than seed data that looks like seed
        // data: someone will screenshot it, and nobody should have to guess.
        $this->assertStringContainsString('sample content', $text);
    }

    public function test_seeding_twice_does_not_duplicate_the_articles(): void
    {
        $publications = Publication::count();
        $galleys = PublicationGalley::count();

        $this->seed(SiteContentSeeder::class);

        $this->assertSame($publications, Publication::count());
        $this->assertSame($galleys, PublicationGalley::count(), 'A second seed attached a duplicate set of galleys.');
    }
}
