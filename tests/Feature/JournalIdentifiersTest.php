<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\ActivityLog;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\JournalSetting;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * ISSN, publisher and DOI — the identifiers that make a journal citable.
 *
 * All of these were already read by the DOAJ, Crossref and DataCite integrations and had nowhere
 * to be entered, so they were permanently empty. The tests that matter most here are the ones
 * about refusing bad input: a malformed ISSN or a duplicated DOI propagates out to registries and
 * is painful to retract once deposited.
 */
class JournalIdentifiersTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $manager;

    private Submission $submission;

    private Publication $publication;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create([
            'site_id' => $site->id, 'path' => 'biu', 'title' => ['en' => 'BIU Journal'], 'is_enabled' => true,
        ]);

        $this->manager = User::factory()->create();
        $this->journal->grantRole($this->manager, JournalRole::JournalManager);

        $issue = Issue::create([
            'journal_id' => $this->journal->id, 'volume' => 1, 'number' => 1, 'year' => 2026,
            'is_published' => true, 'publish_at' => now(),
        ]);

        $this->submission = Submission::create([
            'journal_id' => $this->journal->id, 'issue_id' => $issue->id,
            'submitted_by' => $this->manager->id,
            'stage' => SubmissionStage::Production->value, 'submitted_at' => now(),
        ]);

        $this->publication = Publication::create([
            'submission_id' => $this->submission->id, 'version' => 1,
            'title' => ['en' => 'A Paper'], 'status' => 'draft',
        ]);

        $this->submission->update(['current_publication_id' => $this->publication->id]);
    }

    private function settingsRoute(): string
    {
        return route('journal.settings.update', ['journal' => 'biu']);
    }

    private function doiRoute(string $action = 'update'): string
    {
        return route("journal.submissions.doi.{$action}", ['journal' => 'biu', 'submission' => $this->submission->id]);
    }

    // ---------- Journal settings ----------

    public function test_the_settings_screen_shows_what_is_currently_stored(): void
    {
        JournalSetting::create(['journal_id' => $this->journal->id, 'name' => 'issn_online', 'locale' => '', 'value' => '2789-1234']);

        $this->actingAs($this->manager)
            ->get(route('journal.settings.edit', ['journal' => 'biu']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('journal/settings')
                ->where('settings.issn_online', '2789-1234')
                ->where('settings.publisher', ''));
    }

    public function test_a_manager_can_save_the_identifiers(): void
    {
        $this->actingAs($this->manager)->put($this->settingsRoute(), [
            'publisher' => 'BELTEI International University',
            'issn_online' => '2789-1234',
            'issn_print' => '2789-5678',
            'doi_prefix' => '10.5555',
        ])->assertSessionHasNoErrors();

        $journal = $this->journal->fresh();
        $this->assertSame('BELTEI International University', $journal->setting('publisher'));
        $this->assertSame('2789-1234', $journal->setting('issn_online'));
        $this->assertSame('10.5555', $journal->setting('doi_prefix'));
    }

    public function test_a_malformed_issn_is_refused(): void
    {
        $this->actingAs($this->manager)
            ->put($this->settingsRoute(), ['issn_online' => '12345678'])
            ->assertSessionHasErrors('issn_online');

        $this->assertNull($this->journal->fresh()->setting('issn_online'));
    }

    public function test_an_issn_ending_in_x_is_accepted(): void
    {
        // A real ISSN check digit can be X. Rejecting it would refuse valid journals.
        $this->actingAs($this->manager)
            ->put($this->settingsRoute(), ['issn_online' => '2434-561X'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2434-561X', $this->journal->fresh()->setting('issn_online'));
    }

    public function test_a_doi_prefix_that_is_not_a_prefix_is_refused(): void
    {
        $this->actingAs($this->manager)
            ->put($this->settingsRoute(), ['doi_prefix' => 'biu-journal'])
            ->assertSessionHasErrors('doi_prefix');
    }

    /**
     * Clearing a field must remove the row, not store an empty string — DoiPatternGenerator tests
     * the prefix for truthiness, and an empty string would read as "configured" and generate
     * "/biu.v1.i1.a42" with no prefix at all.
     */
    public function test_clearing_a_setting_removes_it_rather_than_storing_blank(): void
    {
        $this->actingAs($this->manager)->put($this->settingsRoute(), ['doi_prefix' => '10.5555']);
        $this->actingAs($this->manager)->put($this->settingsRoute(), ['doi_prefix' => '']);

        $this->assertNull($this->journal->fresh()->setting('doi_prefix'));
        $this->assertSame(0, JournalSetting::where('journal_id', $this->journal->id)->where('name', 'doi_prefix')->count());
    }

    public function test_saving_identifiers_leaves_unrelated_settings_alone(): void
    {
        // The AI consent flag and the workflow timings are owned by other screens; a save here
        // must not be able to revoke consent as a side effect.
        JournalSetting::create(['journal_id' => $this->journal->id, 'name' => 'ai_enabled', 'locale' => '', 'value' => true]);

        $this->actingAs($this->manager)->put($this->settingsRoute(), ['publisher' => 'BIU']);

        $this->assertTrue((bool) $this->journal->fresh()->setting('ai_enabled'));
    }

    public function test_someone_who_does_not_run_the_journal_cannot_change_its_identifiers(): void
    {
        $reviewer = User::factory()->create();
        $this->journal->grantRole($reviewer, JournalRole::Reviewer);

        $this->actingAs($reviewer)->get(route('journal.settings.edit', ['journal' => 'biu']))->assertForbidden();
        $this->actingAs($reviewer)->put($this->settingsRoute(), ['publisher' => 'Nope'])->assertForbidden();

        $this->assertNull($this->journal->fresh()->setting('publisher'));
    }

    // ---------- Article DOI ----------

    public function test_an_editor_can_assign_a_doi_by_hand(): void
    {
        $this->actingAs($this->manager)
            ->put($this->doiRoute(), ['doi' => '10.5555/biu.v1.i1.a7'])
            ->assertSessionHasNoErrors();

        $this->assertSame('10.5555/biu.v1.i1.a7', $this->publication->fresh()->doi);
    }

    public function test_a_doi_can_be_generated_from_the_journals_prefix(): void
    {
        JournalSetting::create(['journal_id' => $this->journal->id, 'name' => 'doi_prefix', 'locale' => '', 'value' => '10.5555']);

        $this->actingAs($this->manager)->post($this->doiRoute('generate'))->assertSessionHasNoErrors();

        $this->assertSame(
            "10.5555/biu.v1.i1.a{$this->publication->id}",
            $this->publication->fresh()->doi,
        );
    }

    public function test_generating_without_a_prefix_explains_where_to_set_one(): void
    {
        $this->actingAs($this->manager)->post($this->doiRoute('generate'))->assertSessionHasErrors('doi');

        $this->assertNull($this->publication->fresh()->doi);
    }

    public function test_a_malformed_doi_is_refused(): void
    {
        $this->actingAs($this->manager)
            ->put($this->doiRoute(), ['doi' => 'not-a-doi'])
            ->assertSessionHasErrors('doi');

        $this->assertNull($this->publication->fresh()->doi);
    }

    /**
     * Two articles sharing a DOI propagates into Crossref, DataCite, ORCID and every exported
     * citation, and is painful to retract once registered.
     */
    public function test_a_doi_already_used_by_another_article_is_refused(): void
    {
        $other = Publication::create([
            'submission_id' => $this->submission->id, 'version' => 2,
            'title' => ['en' => 'Another'], 'status' => 'draft', 'doi' => '10.5555/taken',
        ]);

        $this->actingAs($this->manager)
            ->put($this->doiRoute(), ['doi' => '10.5555/taken'])
            ->assertSessionHasErrors('doi');

        $this->assertNull($this->publication->fresh()->doi);
        $this->assertSame('10.5555/taken', $other->fresh()->doi);
    }

    public function test_a_doi_can_be_removed(): void
    {
        $this->publication->update(['doi' => '10.5555/biu.v1.i1.a7']);

        $this->actingAs($this->manager)->put($this->doiRoute(), ['doi' => ''])->assertSessionHasNoErrors();

        $this->assertNull($this->publication->fresh()->doi);
    }

    public function test_assigning_a_doi_is_recorded_on_the_timeline(): void
    {
        $this->actingAs($this->manager)->put($this->doiRoute(), ['doi' => '10.5555/biu.v1.i1.a7']);

        $this->assertTrue(
            ActivityLog::where('action', 'submission.doi_assigned')
                ->where('subject_id', $this->submission->id)
                ->exists(),
        );
    }

    public function test_a_reviewer_cannot_assign_a_doi(): void
    {
        $reviewer = User::factory()->create();
        $this->journal->grantRole($reviewer, JournalRole::Reviewer);

        $this->actingAs($reviewer)->put($this->doiRoute(), ['doi' => '10.5555/nope'])->assertForbidden();

        $this->assertNull($this->publication->fresh()->doi);
    }

    public function test_the_doi_reaches_the_public_article_page_and_its_citations(): void
    {
        $this->publication->update(['doi' => '10.5555/biu.v1.i1.a7', 'status' => 'published', 'published_at' => now()]);
        $this->submission->update(['stage' => SubmissionStage::Published->value]);

        $this->get("/journal/biu/article/{$this->publication->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('article.doi', '10.5555/biu.v1.i1.a7'));

        $this->get("/journal/biu/article/{$this->publication->id}/cite/bibtex")
            ->assertOk()
            ->assertSee('10.5555/biu.v1.i1.a7');
    }
}
