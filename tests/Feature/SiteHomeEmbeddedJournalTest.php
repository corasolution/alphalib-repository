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
 * Showing one journal's issues on the site homepage, for a library that has a single main journal.
 *
 * The homepage has no journal in context, so these run with the console scope bypass off: with it
 * on, JournalScope lets everything through and a green test would not prove the real request works.
 */
class SiteHomeEmbeddedJournalTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private Journal $journal;

    protected function setUp(): void
    {
        parent::setUp();

        config(['journal.scope_console_bypass' => false]);

        $this->site = Site::create(['title' => 'Library']);
        $this->journal = Journal::create(['site_id' => $this->site->id, 'path' => 'main', 'title' => ['en' => 'Main Journal'], 'is_enabled' => true]);
    }

    private function publishArticle(Journal $journal, string $title, SubmissionStage $stage = SubmissionStage::Published): void
    {
        $issue = Issue::withoutGlobalScopes()->firstOrCreate(
            ['journal_id' => $journal->id],
            ['volume' => 1, 'number' => 1, 'is_published' => true, 'publish_at' => now()],
        );

        $submission = Submission::withoutGlobalScopes()->create([
            'journal_id' => $journal->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => $stage->value,
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
    }

    public function test_by_default_the_homepage_lists_journals_and_features_none(): void
    {
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('featuredJournal', null)
            ->has('journals', 1)
        );
    }

    public function test_a_chosen_journal_shows_its_current_issue_on_the_homepage(): void
    {
        $this->publishArticle($this->journal, 'Rice Yields in Takeo');
        $this->site->update(['settings' => ['embed_journal_id' => $this->journal->id]]);

        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('site/home')
            ->where('featuredJournal.journal.path', 'main')
            ->where('featuredJournal.currentIssue.identification', 'Vol. 1, No. 1')
            ->has('featuredJournal.currentIssue.articles', 1)
            ->where('featuredJournal.currentIssue.articles.0.title', 'Rice Yields in Takeo')
        );
    }

    public function test_only_the_chosen_journals_articles_appear(): void
    {
        $other = Journal::create(['site_id' => $this->site->id, 'path' => 'other', 'title' => ['en' => 'Other'], 'is_enabled' => true]);
        $this->publishArticle($this->journal, 'Mine');
        $this->publishArticle($other, 'Not Mine');
        $this->site->update(['settings' => ['embed_journal_id' => $this->journal->id]]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('featuredJournal.currentIssue.articles', 1)
            ->where('featuredJournal.currentIssue.articles.0.title', 'Mine')
        );
    }

    public function test_a_withdrawn_article_does_not_leak_onto_the_homepage(): void
    {
        $this->publishArticle($this->journal, 'Withdrawn', SubmissionStage::Production);
        $this->site->update(['settings' => ['embed_journal_id' => $this->journal->id]]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->has('featuredJournal.currentIssue.articles', 0));
    }

    public function test_a_disabled_journal_is_not_featured(): void
    {
        $this->site->update(['settings' => ['embed_journal_id' => $this->journal->id]]);
        $this->journal->update(['is_enabled' => false]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('featuredJournal', null));
    }

    public function test_the_redirect_still_wins_when_both_are_set(): void
    {
        $this->site->update(['redirect_journal_id' => $this->journal->id, 'settings' => ['embed_journal_id' => $this->journal->id]]);

        $this->get('/')->assertRedirect(route('journal.public.show', ['journal' => 'main']));
    }

    public function test_a_site_admin_can_choose_and_clear_the_featured_journal(): void
    {
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->put('/cms/homepage', ['title' => 'Library', 'embed_journal_id' => $this->journal->id])->assertSessionHasNoErrors();
        $this->assertSame($this->journal->id, $this->site->fresh()->settings['embed_journal_id']);

        $this->actingAs($admin)->put('/cms/homepage', ['title' => 'Library', 'embed_journal_id' => ''])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('embed_journal_id', $this->site->fresh()->settings ?? []);
    }
}
