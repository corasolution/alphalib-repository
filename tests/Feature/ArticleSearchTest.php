<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Contributor;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Section;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Reader-facing article search.
 *
 * The tests that matter most are not the ones proving a match is found — they are the ones
 * proving what is *not* returned. Search reaches across every journal at once, so it is the
 * likeliest place for an unpublished manuscript or a disabled journal's article to leak into
 * public view.
 */
class ArticleSearchTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private Site $site;

    private Issue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create([
            'site_id' => $this->site->id, 'path' => 'biu', 'title' => ['en' => 'BIU Journal'], 'is_enabled' => true,
        ]);
        $this->issue = Issue::create([
            'journal_id' => $this->journal->id, 'volume' => 1, 'number' => 1, 'year' => 2026,
            'is_published' => true, 'publish_at' => now(),
        ]);
    }

    private function publish(
        string $title,
        ?string $abstract = null,
        array $authors = [],
        ?Journal $journal = null,
        string $stage = SubmissionStage::Published->value,
        string $status = 'published',
        ?string $publishedAt = null,
    ): Publication {
        $journal ??= $this->journal;

        $submission = Submission::create([
            'journal_id' => $journal->id,
            'issue_id' => $journal->is($this->journal) ? $this->issue->id : null,
            'submitted_by' => User::factory()->create()->id,
            'stage' => $stage,
            'submitted_at' => now(),
        ]);

        $publication = Publication::create([
            'submission_id' => $submission->id,
            'version' => 1,
            'title' => ['en' => $title],
            'abstract' => $abstract ? ['en' => $abstract] : null,
            'status' => $status,
            'published_at' => $publishedAt ?? now(),
        ]);

        $submission->update(['current_publication_id' => $publication->id]);

        foreach ($authors as $i => $name) {
            [$given, $family] = array_pad(explode(' ', $name, 2), 2, '');
            Contributor::create([
                'publication_id' => $publication->id,
                'given_name' => $given, 'family_name' => $family,
                'role' => 'author', 'sequence' => $i,
            ]);
        }

        return $publication;
    }

    /** @return array<int, string> the titles returned, in order */
    private function search(array $params): array
    {
        $titles = [];

        $this->get('/search?'.http_build_query($params))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$titles) {
                foreach ($page->toArray()['props']['results']['data'] ?? [] as $row) {
                    $titles[] = $row['title'];
                }
            });

        return $titles;
    }

    public function test_the_search_page_is_public_and_opens_empty(): void
    {
        $this->publish('Rice Yield in the Mekong Delta');

        // No criteria means no result set — showing every article on the site would look like a
        // search nobody ran.
        $this->get('/search')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public/search')
                ->where('searched', false)
                ->where('results', null));
    }

    public function test_it_finds_an_article_by_a_word_in_the_title(): void
    {
        $this->publish('Rice Yield in the Mekong Delta');
        $this->publish('Seagrass Recovery in Kampot Bay');

        $this->assertSame(['Rice Yield in the Mekong Delta'], $this->search(['q' => 'mekong']));
    }

    public function test_it_finds_an_article_by_a_word_in_the_abstract(): void
    {
        $this->publish('A Paper', 'We surveyed forty-two reef sites across three seasons.');
        $this->publish('Another Paper', 'Nothing to do with the sea.');

        $this->assertSame(['A Paper'], $this->search(['q' => 'reef']));
    }

    public function test_it_finds_an_article_by_author_name_in_either_order(): void
    {
        $this->publish('Lifelong Learning', null, ['Ly Chheng']);
        $this->publish('Something Else', null, ['Sreymom Pich']);

        $this->assertSame(['Lifelong Learning'], $this->search(['author' => 'Ly Chheng']));
        $this->assertSame(['Lifelong Learning'], $this->search(['author' => 'Chheng Ly']));
    }

    // ---------- What must never be returned ----------

    public function test_a_submission_that_is_not_published_is_never_returned(): void
    {
        // The double gate. The publication row says published; the submission does not.
        $this->publish('Still Under Review', null, [], null, SubmissionStage::Review->value);

        $this->assertSame([], $this->search(['q' => 'review']));
    }

    public function test_a_draft_publication_is_never_returned(): void
    {
        $this->publish('Draft Article', null, [], null, SubmissionStage::Published->value, 'draft');

        $this->assertSame([], $this->search(['q' => 'draft']));
    }

    public function test_an_article_in_a_disabled_journal_is_never_returned(): void
    {
        // A disabled journal's pages 404, so search must not be the back door to its articles.
        $hidden = Journal::create([
            'site_id' => $this->site->id, 'path' => 'hidden', 'title' => ['en' => 'Hidden'], 'is_enabled' => false,
        ]);
        $this->publish('Secret Findings', null, [], $hidden);

        $this->assertSame([], $this->search(['q' => 'secret']));
    }

    // ---------- Advanced criteria ----------

    public function test_advanced_fields_narrow_rather_than_widen(): void
    {
        $this->publish('Rice Yield Study', null, ['Ly Chheng']);
        $this->publish('Rice Growing Regions', null, ['Sreymom Pich']);

        // Both match the title fragment; only one matches the author too.
        $this->assertCount(2, $this->search(['title' => 'Rice']));
        $this->assertSame(['Rice Yield Study'], $this->search(['title' => 'Rice', 'author' => 'Chheng']));
    }

    public function test_it_filters_by_journal(): void
    {
        $other = Journal::create([
            'site_id' => $this->site->id, 'path' => 'other', 'title' => ['en' => 'Other'], 'is_enabled' => true,
        ]);
        $this->publish('Here', null, [], $this->journal);
        $this->publish('There', null, [], $other);

        $this->assertSame(['Here'], $this->search(['journal' => 'biu']));
        $this->assertSame(['There'], $this->search(['journal' => 'other']));
    }

    public function test_it_filters_by_year_range(): void
    {
        $this->publish('Old Paper', null, [], null, SubmissionStage::Published->value, 'published', '2020-06-01 00:00:00');
        $this->publish('New Paper', null, [], null, SubmissionStage::Published->value, 'published', '2026-06-01 00:00:00');

        $this->assertSame(['Old Paper'], $this->search(['q' => 'paper', 'year_to' => 2021]));
        $this->assertSame(['New Paper'], $this->search(['q' => 'paper', 'year_from' => 2025]));
    }

    public function test_it_filters_by_section(): void
    {
        $section = Section::create(['journal_id' => $this->journal->id, 'title' => ['en' => 'Reviews'], 'sort_order' => 0]);
        $inSection = $this->publish('A Review Article');
        $inSection->submission->update(['section_id' => $section->id]);
        $this->publish('An Ordinary Article');

        $this->assertSame(['A Review Article'], $this->search(['section' => $section->id]));
    }

    public function test_it_finds_an_article_by_doi(): void
    {
        $article = $this->publish('Identified Paper');
        $article->update(['doi' => '10.5555/biu.v1.i1.a7']);
        $this->publish('Unidentified Paper');

        $this->assertSame(['Identified Paper'], $this->search(['doi' => '10.5555/biu']));
    }

    public function test_sorting_by_date_works_in_both_directions(): void
    {
        $this->publish('First Paper', null, [], null, SubmissionStage::Published->value, 'published', '2020-01-01 00:00:00');
        $this->publish('Second Paper', null, [], null, SubmissionStage::Published->value, 'published', '2026-01-01 00:00:00');

        $this->assertSame(['Second Paper', 'First Paper'], $this->search(['title' => 'Paper', 'sort' => 'newest']));
        $this->assertSame(['First Paper', 'Second Paper'], $this->search(['title' => 'Paper', 'sort' => 'oldest']));
    }

    public function test_a_query_matching_nothing_returns_an_empty_result_rather_than_an_error(): void
    {
        $this->publish('Rice Yield');

        $this->get('/search?q=xyzzynotathing')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('searched', true)
                ->where('results.total', 0));
    }

    public function test_an_absurd_sort_value_is_refused_rather_than_interpolated(): void
    {
        // The sort feeds an order-by expression, so anything but a known key must be rejected.
        $this->get('/search?q=rice&sort=title;DROP+TABLE+publications')->assertSessionHasErrors('sort');
    }

    /**
     * The bug this guards against shipped to production once already.
     *
     * JournalScope denies by default when no journal is in context, and a public search request
     * has none. The *filters* were opted out explicitly; the eager loads were not — so every
     * result came back with journal, issue and section null, and each result linked to
     * "/journal/undefined/article/{id}". The suite stayed green because JournalScope bypasses
     * entirely under `artisan test`, which is exactly what this config flag turns off.
     */
    public function test_results_carry_their_journal_when_the_scope_is_really_enforced(): void
    {
        $section = Section::create(['journal_id' => $this->journal->id, 'title' => ['en' => 'Articles'], 'sort_order' => 0]);
        $article = $this->publish('Scoped Metadata Paper');
        $article->submission->update(['section_id' => $section->id]);
        $issueLabel = $this->issue->identification();

        // Flipped only now: with the bypass off even this test's own fixture setup would be
        // denied. What is under test is the request, not the arranging.
        config(['journal.scope_console_bypass' => false]);

        $this->get('/search?q=scoped')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('results.data.0.journal.path', 'biu')
                ->where('results.data.0.journal.title', 'BIU Journal')
                ->where('results.data.0.section', 'Articles')
                ->where('results.data.0.issue', $issueLabel));
    }
    public function test_sections_are_only_offered_for_the_journal_being_filtered(): void
    {
        Section::create(['journal_id' => $this->journal->id, 'title' => ['en' => 'Reviews'], 'sort_order' => 0]);

        $this->get('/search')->assertInertia(fn (AssertableInertia $page) => $page->has('sections', 0));
        $this->get('/search?journal=biu')->assertInertia(fn (AssertableInertia $page) => $page->has('sections', 1));
    }
}
