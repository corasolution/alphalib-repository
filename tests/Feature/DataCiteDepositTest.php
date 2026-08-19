<?php

namespace Tests\Feature;

use Alpha\Journal\DataCite\DataCiteDepositService;
use Alpha\Journal\DataCite\DataCiteDoiMinter;
use Alpha\Journal\DataCite\DataCiteXmlBuilder;
use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Jobs\DepositToCrossrefJob;
use Alpha\Journal\Jobs\DepositToDataciteJob;
use Alpha\Journal\Models\DataciteDeposit;
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

class DataCiteDepositTest extends TestCase
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
        $issue = Issue::create(['journal_id' => $this->journal->id, 'volume' => 2, 'number' => 1, 'year' => 2026]);
        $this->submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $author->id,
            'stage' => SubmissionStage::Published->value,
            'issue_id' => $issue->id,
        ]);
        $this->publication = $this->submission->publications()->create([
            'title' => ['en' => 'Mangrove Carbon Stocks'],
            'abstract' => ['en' => 'We estimate carbon stocks.'],
            'status' => 'published',
            'published_at' => now(),
            'doi' => '10.5555/demo.v2.i1.a1',
        ]);
        $this->submission->update(['current_publication_id' => $this->publication->id]);
        $this->publication->contributors()->create([
            'given_name' => 'Grace', 'family_name' => 'Hopper', 'orcid' => '0000-0001-5109-3700', 'sequence' => 1,
        ]);
    }

    public function test_the_datacite_xml_contains_the_expected_fields(): void
    {
        $xml = app(DataCiteXmlBuilder::class)->build($this->publication, '10.5555/demo.v2.i1.a1');

        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc, 'DataCite XML is not well-formed');
        $this->assertStringContainsString('<identifier identifierType="DOI">10.5555/demo.v2.i1.a1</identifier>', $xml);
        $this->assertStringContainsString('<title>Mangrove Carbon Stocks</title>', $xml);
        $this->assertStringContainsString('<familyName>Hopper</familyName>', $xml);
        $this->assertStringContainsString('https://orcid.org/0000-0001-5109-3700', $xml);
        $this->assertStringContainsString('resourceTypeGeneral="Text"', $xml);
        $this->assertStringContainsString('We estimate carbon stocks.', $xml);
    }

    public function test_deposit_requires_a_published_publication(): void
    {
        $this->publication->update(['status' => 'draft']);

        $this->expectException(InvalidArgumentException::class);

        app(DataCiteDepositService::class)->deposit($this->publication);
    }

    public function test_deposit_reuses_an_existing_doi_rather_than_regenerating(): void
    {
        Queue::fake();

        $deposit = app(DataCiteDepositService::class)->deposit($this->publication);

        $this->assertSame('10.5555/demo.v2.i1.a1', $deposit->doi);
    }

    public function test_a_successful_deposit_marks_the_record_submitted(): void
    {
        Http::fake(['api.test.datacite.org/*' => Http::response(['data' => ['id' => '10.5555/demo.v2.i1.a1']], 201)]);
        $deposit = DataciteDeposit::create(['publication_id' => $this->publication->id, 'doi' => $this->publication->doi, 'status' => 'pending']);

        (new DepositToDataciteJob($deposit))->handle(app(DataCiteXmlBuilder::class), app(DataCiteDoiMinter::class));

        $this->assertSame('submitted', $deposit->fresh()->status);
    }

    public function test_a_failed_deposit_marks_the_record_failed_and_throws(): void
    {
        Http::fake(['api.test.datacite.org/*' => Http::response('unauthorized', 401)]);
        $deposit = DataciteDeposit::create(['publication_id' => $this->publication->id, 'doi' => $this->publication->doi, 'status' => 'pending']);

        $this->expectException(\RuntimeException::class);

        try {
            (new DepositToDataciteJob($deposit))->handle(app(DataCiteXmlBuilder::class), app(DataCiteDoiMinter::class));
        } finally {
            $this->assertSame('failed', $deposit->fresh()->status);
        }
    }

    public function test_status_reports_the_local_tracking_record(): void
    {
        DataciteDeposit::create(['publication_id' => $this->publication->id, 'doi' => '10.5555/x', 'status' => 'submitted']);

        $receipt = app(DataCiteDoiMinter::class)->status('10.5555/x');

        $this->assertSame('submitted', $receipt->status);
    }

    private function publishViaWorkflow(): void
    {
        $editor = User::factory()->create();
        $this->journal->grantRole($editor, JournalRole::Editor);
        StageAssignment::create([
            'submission_id' => $this->submission->id, 'user_id' => $editor->id,
            'stage' => SubmissionStage::Production->value, 'role' => JournalRole::Editor->value,
        ]);
        $this->submission->update(['stage' => SubmissionStage::Production->value]);
        $this->publication->update(['status' => 'draft', 'published_at' => null]);

        app(SubmissionWorkflow::class)->recordDecision($this->submission, $editor, EditorialDecisionType::Publish);
    }

    public function test_publishing_deposits_to_crossref_when_the_journal_has_a_doi_prefix(): void
    {
        Queue::fake();
        $this->journal->settings()->create(['name' => 'doi_prefix', 'value' => '10.5555']);

        $this->publishViaWorkflow();

        Queue::assertPushed(DepositToCrossrefJob::class);
    }

    public function test_publishing_does_not_deposit_to_crossref_without_a_doi_prefix(): void
    {
        Queue::fake();

        $this->publishViaWorkflow();

        Queue::assertNotPushed(DepositToCrossrefJob::class);
    }

    public function test_publishing_deposits_to_datacite_only_when_both_installation_and_journal_are_configured(): void
    {
        Queue::fake();
        config(['services.datacite.username' => 'installation-account']);
        $this->journal->settings()->create(['name' => 'doi_prefix', 'value' => '10.5555']);

        $this->publishViaWorkflow();

        Queue::assertPushed(DepositToDataciteJob::class);
    }

    public function test_publishing_does_not_deposit_to_datacite_when_the_installation_has_no_credentials(): void
    {
        Queue::fake();
        $this->journal->settings()->create(['name' => 'doi_prefix', 'value' => '10.5555']);
        // No services.datacite.username configured.

        $this->publishViaWorkflow();

        Queue::assertNotPushed(DepositToDataciteJob::class);
    }
}
