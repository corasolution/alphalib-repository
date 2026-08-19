<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Export\ArticleCsvExporter;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Section;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Privacy\AccountAnonymizationService;
use Alpha\Journal\Privacy\UserDataExportService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class PrivacyAndExportTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
    }

    public function test_csv_export_contains_a_row_per_published_article(): void
    {
        $section = Section::create(['journal_id' => $this->journal->id, 'title' => ['en' => 'Articles']]);
        $issue = Issue::create(['journal_id' => $this->journal->id, 'volume' => 3, 'number' => 2, 'year' => 2026]);
        $author = User::factory()->create();
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'section_id' => $section->id,
            'submitted_by' => $author->id,
            'stage' => SubmissionStage::Published->value,
            'issue_id' => $issue->id,
        ]);
        $publication = $submission->publications()->create([
            'title' => ['en' => 'Wetland Restoration Outcomes'],
            'status' => 'published',
            'published_at' => '2026-04-01',
            'doi' => '10.5555/demo.v3.i2.a1',
        ]);
        $submission->update(['current_publication_id' => $publication->id]);
        $publication->contributors()->create(['given_name' => 'Marie', 'family_name' => 'Curie', 'sequence' => 1]);

        $csv = app(ArticleCsvExporter::class)->export($this->journal);

        $this->assertStringContainsString('DOI,Title,Authors,Section,Volume,Issue,Year,"Published Date"', $csv);
        $this->assertStringContainsString('10.5555/demo.v3.i2.a1,"Wetland Restoration Outcomes","Marie Curie",Articles,3,2,2026,2026-04-01', $csv);
    }

    public function test_csv_export_excludes_unpublished_submissions(): void
    {
        $author = User::factory()->create();
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $author->id,
            'stage' => SubmissionStage::Submission->value,
        ]);
        $submission->publications()->create(['title' => ['en' => 'Draft Article'], 'status' => 'draft']);

        $csv = app(ArticleCsvExporter::class)->export($this->journal);

        $this->assertStringNotContainsString('Draft Article', $csv);
    }

    public function test_data_export_includes_submissions_reviews_and_decisions(): void
    {
        $user = User::factory()->create();
        $this->journal->grantRole($user, JournalRole::Editor);
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $user->id,
            'stage' => SubmissionStage::Submission->value,
        ]);
        StageAssignment::create([
            'submission_id' => $submission->id, 'user_id' => $user->id,
            'stage' => SubmissionStage::Submission->value, 'role' => JournalRole::Editor->value,
        ]);
        $submission->editorialDecisions()->create([
            'actor_id' => $user->id, 'role_used' => 'editor',
            'decision' => EditorialDecisionType::SendToReview->value, 'target_stage' => 'review',
        ]);

        $data = app(UserDataExportService::class)->export($user);

        $this->assertSame($user->email, $data['profile']['email']);
        $this->assertCount(1, $data['journal_roles']);
        $this->assertCount(1, $data['submissions_authored']);
        $this->assertCount(1, $data['editorial_decisions_made']);
    }

    public function test_the_data_export_endpoint_requires_authentication(): void
    {
        $this->getJson('/settings/data-export')->assertUnauthorized();
    }

    public function test_the_data_export_endpoint_returns_the_authenticated_users_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/settings/data-export');

        $response->assertOk()->assertJsonPath('profile.email', $user->email);
    }

    public function test_anonymizing_scrubs_pii_but_keeps_the_row(): void
    {
        $user = User::factory()->create(['email' => 'real@example.com', 'orcid' => '0000-0002-1825-0097']);

        app(AccountAnonymizationService::class)->anonymize($user);

        $fresh = $user->fresh();
        $this->assertNotSame('real@example.com', $fresh->email);
        $this->assertSame('Deleted User', $fresh->name);
        $this->assertNull($fresh->orcid);
        $this->assertTrue($fresh->is_suspended);
        $this->assertNotNull($fresh->anonymized_at);
    }

    public function test_anonymizing_twice_is_rejected(): void
    {
        $user = User::factory()->create();
        app(AccountAnonymizationService::class)->anonymize($user);

        $this->expectException(InvalidArgumentException::class);

        app(AccountAnonymizationService::class)->anonymize($user->fresh());
    }

    public function test_editorial_decisions_survive_anonymization_intact(): void
    {
        $user = User::factory()->create();
        $this->journal->grantRole($user, JournalRole::Editor);
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $user->id,
            'stage' => SubmissionStage::Submission->value,
        ]);
        $decision = $submission->editorialDecisions()->create([
            'actor_id' => $user->id, 'role_used' => 'editor',
            'decision' => EditorialDecisionType::SendToReview->value, 'target_stage' => 'review',
        ]);

        app(AccountAnonymizationService::class)->anonymize($user);

        $this->assertDatabaseHas('editorial_decisions', ['id' => $decision->id, 'actor_id' => $user->id]);
    }

    public function test_the_database_refuses_to_hard_delete_a_user_with_editorial_history(): void
    {
        $user = User::factory()->create();
        $this->journal->grantRole($user, JournalRole::Editor);
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $user->id,
            'stage' => SubmissionStage::Submission->value,
        ]);
        $submission->editorialDecisions()->create([
            'actor_id' => $user->id, 'role_used' => 'editor',
            'decision' => EditorialDecisionType::SendToReview->value, 'target_stage' => 'review',
        ]);

        $this->expectException(QueryException::class);

        DB::table('users')->where('id', $user->id)->delete();
    }
}
