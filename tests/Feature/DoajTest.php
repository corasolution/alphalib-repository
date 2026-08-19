<?php

namespace Tests\Feature;

use Alpha\Journal\Doaj\DoajApplicationBundle;
use Alpha\Journal\Doaj\DoajArticleBuilder;
use Alpha\Journal\Doaj\DoajClient;
use Alpha\Journal\Doaj\DoajDepositService;
use Alpha\Journal\Doaj\DoajException;
use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Jobs\DepositToDoajJob;
use Alpha\Journal\Models\DoajDeposit;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Workflow\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

class DoajTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private Submission $submission;

    private Publication $publication;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
        $author = User::factory()->create();
        $issue = Issue::create(['journal_id' => $this->journal->id, 'volume' => 4, 'number' => 1, 'year' => 2026]);
        $this->submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $author->id,
            'stage' => SubmissionStage::Published->value,
            'issue_id' => $issue->id,
            'submitted_at' => now()->subDays(60),
        ]);
        $this->publication = $this->submission->publications()->create([
            'title' => ['en' => 'Groundwater Recharge in Karst Terrain'],
            'abstract' => ['en' => 'We measure recharge rates.'],
            'status' => 'published',
            'published_at' => now()->subDays(10),
            'doi' => '10.5555/demo.v4.i1.a1',
        ]);
        $this->submission->update(['current_publication_id' => $this->publication->id]);
        $this->publication->contributors()->create([
            'given_name' => 'Ada', 'family_name' => 'Lovelace', 'orcid' => '0000-0002-1825-0097', 'affiliation' => 'Analytical Engine Institute', 'sequence' => 1,
        ]);
    }

    public function test_the_article_payload_contains_the_expected_bibjson_fields(): void
    {
        $this->journal->settings()->create(['name' => 'publisher', 'value' => 'Demo University Press']);
        $this->journal->settings()->create(['name' => 'issn_online', 'value' => '1234-5678']);

        $payload = app(DoajArticleBuilder::class)->build($this->publication);

        $bibjson = $payload['bibjson'];
        $this->assertSame('Groundwater Recharge in Karst Terrain', $bibjson['title']);
        $this->assertSame('Ada Lovelace', $bibjson['author'][0]['name']);
        $this->assertSame('https://orcid.org/0000-0002-1825-0097', $bibjson['author'][0]['orcid_id']);
        $this->assertSame([['type' => 'doi', 'id' => '10.5555/demo.v4.i1.a1']], $bibjson['identifier']);
        $this->assertSame('Demo University Press', $bibjson['journal']['publisher']);
        $this->assertSame(['1234-5678'], $bibjson['journal']['issns']);
        $this->assertSame('4', $bibjson['journal']['volume']);
    }

    public function test_deposit_is_skipped_when_the_journal_has_no_doaj_api_key(): void
    {
        Queue::fake();

        $deposit = app(DoajDepositService::class)->deposit($this->publication);

        $this->assertNull($deposit);
        Queue::assertNothingPushed();
    }

    public function test_deposit_is_queued_when_the_journal_has_a_doaj_api_key(): void
    {
        Queue::fake();
        $this->journal->settings()->create(['name' => 'doaj_api_key', 'value' => 'test-key']);

        $deposit = app(DoajDepositService::class)->deposit($this->publication);

        $this->assertNotNull($deposit);
        $this->assertSame('pending', $deposit->status);
        Queue::assertPushed(DepositToDoajJob::class, fn ($job) => $job->deposit->is($deposit));
    }

    public function test_deposit_requires_a_published_publication(): void
    {
        $this->publication->update(['status' => 'draft']);

        $this->expectException(InvalidArgumentException::class);

        app(DoajDepositService::class)->deposit($this->publication);
    }

    public function test_publishing_dispatches_the_doaj_listener_only_when_configured(): void
    {
        Queue::fake();
        $this->journal->settings()->create(['name' => 'doaj_api_key', 'value' => 'test-key']);
        $editor = User::factory()->create();
        $this->journal->grantRole($editor, JournalRole::Editor);
        StageAssignment::create([
            'submission_id' => $this->submission->id, 'user_id' => $editor->id,
            'stage' => SubmissionStage::Production->value, 'role' => JournalRole::Editor->value,
        ]);
        $this->submission->update(['stage' => SubmissionStage::Production->value]);
        $this->publication->update(['status' => 'draft', 'published_at' => null]);

        app(SubmissionWorkflow::class)->recordDecision($this->submission, $editor, EditorialDecisionType::Publish);

        Queue::assertPushed(DepositToDoajJob::class);
    }

    public function test_a_successful_upload_marks_the_deposit_submitted_with_the_doaj_id(): void
    {
        Http::fake(['doaj.org/*' => Http::response(['id' => 'doaj-article-123'], 201)]);
        $this->journal->settings()->create(['name' => 'doaj_api_key', 'value' => 'test-key']);
        $deposit = DoajDeposit::create(['publication_id' => $this->publication->id, 'status' => 'pending']);

        (new DepositToDoajJob($deposit))->handle(app(DoajArticleBuilder::class), app(DoajClient::class));

        $fresh = $deposit->fresh();
        $this->assertSame('submitted', $fresh->status);
        $this->assertSame('doaj-article-123', $fresh->doaj_article_id);
    }

    public function test_a_failed_upload_marks_the_deposit_failed_and_throws(): void
    {
        Http::fake(['doaj.org/*' => Http::response('bad request', 400)]);
        $this->journal->settings()->create(['name' => 'doaj_api_key', 'value' => 'test-key']);
        $deposit = DoajDeposit::create(['publication_id' => $this->publication->id, 'status' => 'pending']);

        $this->expectException(DoajException::class);

        try {
            (new DepositToDoajJob($deposit))->handle(app(DoajArticleBuilder::class), app(DoajClient::class));
        } finally {
            $this->assertSame('failed', $deposit->fresh()->status);
        }
    }

    public function test_application_bundle_lists_missing_fields_when_unconfigured(): void
    {
        $bundle = app(DoajApplicationBundle::class)->generate($this->journal);

        $this->assertFalse($bundle['is_ready_to_apply']);
        $this->assertContains('Publisher', $bundle['missing_fields']);
        $this->assertSame(1, $bundle['publishing_stats']['published_article_count']);
        $this->assertSame(50.0, $bundle['publishing_stats']['average_days_submission_to_publication']);
    }

    public function test_application_bundle_is_ready_once_all_fields_are_configured(): void
    {
        foreach (['issn_online', 'issn_print', 'publisher', 'country', 'license_url', 'review_process', 'plagiarism_screening', 'apc_amount', 'preservation_policy'] as $key) {
            $this->journal->settings()->create(['name' => $key, 'value' => 'x']);
        }

        $bundle = app(DoajApplicationBundle::class)->generate($this->journal);

        $this->assertTrue($bundle['is_ready_to_apply']);
        $this->assertSame([], $bundle['missing_fields']);
    }
}
