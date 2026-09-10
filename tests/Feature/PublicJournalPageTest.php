<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The public, unauthenticated journal homepage. Every assertion here doubles as proof the page
 * never fabricates content — an unset marketing block is simply absent, and a submission that
 * hasn't cleared both publication gates (Publication.status AND Submission.stage) must never
 * appear, the same double-gate OaiRepository already relies on for the same reason.
 */
class PublicJournalPageTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal'], 'is_enabled' => true]);
    }

    public function test_a_guest_can_view_the_public_journal_page(): void
    {
        $response = $this->get(route('journal.public.show', ['journal' => $this->journal->path]));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/journal-home')
            ->where('journal.title', 'Demo Journal')
        );
    }

    public function test_a_disabled_journal_404s(): void
    {
        $this->journal->update(['is_enabled' => false]);

        $response = $this->get(route('journal.public.show', ['journal' => $this->journal->path]));

        $response->assertNotFound();
    }

    public function test_text_settings_render_only_when_set(): void
    {
        $response = $this->get(route('journal.public.show', ['journal' => $this->journal->path]));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('content', [])
        );

        $this->journal->settings()->create(['name' => 'tagline', 'locale' => 'en', 'value' => 'Advancing the field.']);
        $this->journal->settings()->create(['name' => 'about', 'locale' => 'en', 'value' => 'We publish peer-reviewed research.']);

        $response = $this->get(route('journal.public.show', ['journal' => $this->journal->path]));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('content.tagline', 'Advancing the field.')
            ->where('content.about', 'We publish peer-reviewed research.')
        );
    }

    public function test_a_submission_stuck_mid_pipeline_never_appears_even_if_its_publication_row_says_published(): void
    {
        $issue = Issue::create(['journal_id' => $this->journal->id, 'is_published' => true, 'publish_at' => now()]);

        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => SubmissionStage::Production->value, // not yet Published
            'issue_id' => $issue->id,
        ]);
        $publication = Publication::create([
            'submission_id' => $submission->id,
            'version' => 1,
            'title' => ['en' => 'Not Actually Public Yet'],
            'status' => 'published', // status alone would wrongly suggest it's public
            'published_at' => now(),
        ]);
        $submission->update(['current_publication_id' => $publication->id]);

        $response = $this->get(route('journal.public.show', ['journal' => $this->journal->path]));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('currentIssue.articles', 0));
    }

    public function test_a_fully_published_article_appears_on_the_current_issue(): void
    {
        $issue = Issue::create(['journal_id' => $this->journal->id, 'volume' => 3, 'number' => 1, 'is_published' => true, 'publish_at' => now()]);

        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => SubmissionStage::Published->value,
            'issue_id' => $issue->id,
        ]);
        $publication = Publication::create([
            'submission_id' => $submission->id,
            'version' => 1,
            'title' => ['en' => 'A Fully Published Article'],
            'status' => 'published',
            'published_at' => now(),
        ]);
        $submission->update(['current_publication_id' => $publication->id]);
        $publication->contributors()->create(['given_name' => 'Ada', 'family_name' => 'Lovelace', 'sequence' => 0]);

        $response = $this->get(route('journal.public.show', ['journal' => $this->journal->path]));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('currentIssue.identification', 'Vol. 3, No. 1')
            ->has('currentIssue.articles', 1)
            ->where('currentIssue.articles.0.title', 'A Fully Published Article')
            ->where('currentIssue.articles.0.authors', 'Ada Lovelace')
        );
    }

    public function test_no_published_issue_yet_is_a_graceful_empty_state(): void
    {
        $response = $this->get(route('journal.public.show', ['journal' => $this->journal->path]));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('currentIssue', null)
            ->has('archive', 0)
        );
    }
    /** Builds a published issue holding $articleCount fully-published articles. */
    private function publishedIssue(int $volume, int $number, string $publishAt, int $articleCount = 0): Issue
    {
        $issue = Issue::create([
            'journal_id' => $this->journal->id,
            'volume' => $volume,
            'number' => $number,
            // year is its own column, not derived from publish_at — identification() reads it directly.
            'year' => (int) substr($publishAt, 0, 4),
            'is_published' => true,
            'publish_at' => $publishAt,
        ]);

        foreach (range(1, max($articleCount, 0)) as $i) {
            if ($articleCount === 0) {
                break;
            }

            $this->publishedArticle($issue, "Article {$volume}.{$number}.{$i}");
        }

        return $issue;
    }

    private function publishedArticle(Issue $issue, string $title, string $stage = SubmissionStage::Published->value): Publication
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => $stage,
            'issue_id' => $issue->id,
        ]);

        $publication = Publication::create([
            'submission_id' => $submission->id,
            'version' => 1,
            'title' => ['en' => $title],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $submission->update(['current_publication_id' => $publication->id]);

        return $publication;
    }

    public function test_the_newest_issue_is_current_and_the_rest_are_the_archive(): void
    {
        $this->publishedIssue(1, 1, '2024-01-01');
        $this->publishedIssue(2, 1, '2025-01-01');
        $this->publishedIssue(3, 1, '2026-01-01');

        $response = $this->get(route('journal.public.show', ['journal' => $this->journal->path]));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('currentIssue.identification', 'Vol. 3, No. 1, 2026')
            // Newest first, and the current issue is never repeated in its own archive.
            ->has('archive', 2)
            ->where('archive.0.identification', 'Vol. 2, No. 1, 2025')
            ->where('archive.1.identification', 'Vol. 1, No. 1, 2024')
        );
    }

    public function test_unpublished_issues_never_reach_the_archive(): void
    {
        $this->publishedIssue(1, 1, '2025-01-01');
        $this->publishedIssue(2, 1, '2026-01-01');
        Issue::create([
            'journal_id' => $this->journal->id,
            'volume' => 9,
            'number' => 9,
            'is_published' => false,
            'publish_at' => now(),
        ]);

        $response = $this->get(route('journal.public.show', ['journal' => $this->journal->path]));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('currentIssue.identification', 'Vol. 2, No. 1, 2026')
            ->has('archive', 1)
            ->where('archive.0.identification', 'Vol. 1, No. 1, 2025')
        );
    }

    public function test_archive_article_counts_respect_the_same_double_gate_as_the_current_issue(): void
    {
        $old = $this->publishedIssue(1, 1, '2025-01-01', articleCount: 2);
        $this->publishedIssue(2, 1, '2026-01-01');

        // A publication whose row claims "published" while its submission is still in production.
        // It must not inflate the archive count any more than it may appear in a contents list.
        $this->publishedArticle($old, 'Still In Production', SubmissionStage::Production->value);

        $response = $this->get(route('journal.public.show', ['journal' => $this->journal->path]));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('archive.0.articleCount', 2)
        );
    }

    public function test_another_journals_articles_are_never_counted_in_this_archive(): void
    {
        $this->publishedIssue(1, 1, '2025-01-01', articleCount: 1);
        $this->publishedIssue(2, 1, '2026-01-01');

        $other = Journal::create([
            'site_id' => $this->journal->site_id,
            'path' => 'other',
            'title' => ['en' => 'Other Journal'],
        ]);
        $otherIssue = Issue::create([
            'journal_id' => $other->id,
            'volume' => 1,
            'number' => 1,
            'is_published' => true,
            'publish_at' => '2025-01-01',
        ]);
        $otherSubmission = Submission::create([
            'journal_id' => $other->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => SubmissionStage::Published->value,
            'issue_id' => $otherIssue->id,
        ]);
        Publication::create([
            'submission_id' => $otherSubmission->id,
            'version' => 1,
            'title' => ['en' => 'Someone Elses Article'],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->get(route('journal.public.show', ['journal' => $this->journal->path]));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('archive', 1)
            ->where('archive.0.articleCount', 1)
        );
    }

    public function test_the_page_carries_the_same_site_chrome_as_every_other_public_page(): void
    {
        $site = Site::firstOrFail();
        $site->update(['settings' => [
            'menu_items' => [['label' => 'Ethics', 'url' => '/pages/ethics']],
            'social_links' => ['twitter' => 'https://twitter.com/example'],
            'footer_text' => 'A publishing platform.',
        ]]);

        $response = $this->get(route('journal.public.show', ['journal' => $this->journal->path]));

        // Without these the journal page silently renders a header with no menu and a footer with
        // no icons — the exact regression the CMS content page shipped with.
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('site.title', 'Test Site')
            ->where('site.footerText', 'A publishing platform.')
            ->has('menuItems', 1)
            ->where('menuItems.0.label', 'Ethics')
            ->where('socialLinks.twitter', 'https://twitter.com/example')
        );
    }
}