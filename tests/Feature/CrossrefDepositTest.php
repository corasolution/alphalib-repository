<?php

namespace Tests\Feature;

use Alpha\Journal\Crossref\CrossrefDepositService;
use Alpha\Journal\Crossref\CrossrefDoiMinter;
use Alpha\Journal\Crossref\CrossrefXmlBuilder;
use Alpha\Journal\Crossref\DoiPatternGenerator;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Jobs\DepositToCrossrefJob;
use Alpha\Journal\Models\CrossrefDeposit;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

class CrossrefDepositTest extends TestCase
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
        $issue = Issue::create(['journal_id' => $this->journal->id, 'volume' => 12, 'number' => 3, 'year' => 2026]);
        $this->submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $author->id,
            'stage' => SubmissionStage::Published->value,
            'issue_id' => $issue->id,
        ]);
        $this->publication = $this->submission->publications()->create([
            'title' => ['en' => 'Soil Carbon in the Mekong Delta'],
            'abstract' => ['en' => 'We study soil carbon dynamics.'],
            'status' => 'published',
            'published_at' => '2026-03-15 00:00:00',
        ]);
        $this->submission->update(['current_publication_id' => $this->publication->id]);
        $this->publication->contributors()->create(['given_name' => 'Ada', 'family_name' => 'Lovelace', 'orcid' => '0000-0002-1825-0097', 'sequence' => 1]);
    }

    public function test_doi_pattern_generates_the_expected_doi(): void
    {
        $this->journal->settings()->create(['name' => 'doi_prefix', 'value' => '10.5555']);

        $doi = app(DoiPatternGenerator::class)->generate($this->publication);

        $this->assertSame("10.5555/demo.v12.i3.a{$this->publication->id}", $doi);
    }

    public function test_doi_pattern_can_be_overridden_per_journal(): void
    {
        $this->journal->settings()->create(['name' => 'doi_prefix', 'value' => '10.5555']);
        $this->journal->settings()->create(['name' => 'doi_pattern', 'value' => '{prefix}/article.{articleId}']);

        $doi = app(DoiPatternGenerator::class)->generate($this->publication);

        $this->assertSame("10.5555/article.{$this->publication->id}", $doi);
    }

    public function test_doi_generation_requires_a_configured_prefix(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(DoiPatternGenerator::class)->generate($this->publication);
    }

    public function test_the_deposit_xml_is_well_formed_and_contains_the_expected_metadata(): void
    {
        $xml = app(CrossrefXmlBuilder::class)->build($this->publication, '10.5555/demo.v12.i3.a1');

        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc, 'deposit XML is not well-formed');

        $this->assertStringContainsString('<title>Soil Carbon in the Mekong Delta</title>', $xml);
        $this->assertStringContainsString('<surname>Lovelace</surname>', $xml);
        $this->assertStringContainsString('<given_name>Ada</given_name>', $xml);
        $this->assertStringContainsString('https://orcid.org/0000-0002-1825-0097', $xml);
        $this->assertStringContainsString('We study soil carbon dynamics.', $xml);
        $this->assertStringContainsString('<doi>10.5555/demo.v12.i3.a1</doi>', $xml);
        $this->assertStringContainsString('<year>2026</year>', $xml);
    }

    public function test_deposit_service_rejects_an_unpublished_publication(): void
    {
        $this->publication->update(['status' => 'draft']);

        $this->expectException(InvalidArgumentException::class);

        app(CrossrefDepositService::class)->deposit($this->publication);
    }

    public function test_deposit_service_assigns_a_doi_and_queues_the_job(): void
    {
        Queue::fake();
        $this->journal->settings()->create(['name' => 'doi_prefix', 'value' => '10.5555']);

        $deposit = app(CrossrefDepositService::class)->deposit($this->publication);

        $this->assertSame('pending', $deposit->status);
        $this->assertNotNull($this->publication->fresh()->doi);
        Queue::assertPushed(DepositToCrossrefJob::class, fn ($job) => $job->deposit->is($deposit));
    }

    public function test_deposit_service_does_not_regenerate_an_existing_doi(): void
    {
        Queue::fake();
        $this->publication->update(['doi' => '10.9999/already-set']);

        $deposit = app(CrossrefDepositService::class)->deposit($this->publication);

        $this->assertSame('10.9999/already-set', $deposit->doi);
        $this->assertSame('10.9999/already-set', $this->publication->fresh()->doi);
    }

    public function test_a_successful_http_response_marks_the_deposit_submitted(): void
    {
        Http::fake(['test.crossref.org/*' => Http::response('<html>ok</html>', 200)]);
        $deposit = CrossrefDeposit::create([
            'publication_id' => $this->publication->id,
            'doi' => '10.5555/demo.v12.i3.a1',
            'status' => 'pending',
        ]);

        (new DepositToCrossrefJob($deposit))->handle(app(CrossrefXmlBuilder::class), app(CrossrefDoiMinter::class));

        $fresh = $deposit->fresh();
        $this->assertSame('submitted', $fresh->status);
        $this->assertNotNull($fresh->submitted_at);
        $this->assertSame(1, $fresh->attempts);
    }

    public function test_a_failed_http_response_marks_the_deposit_failed_and_throws(): void
    {
        Http::fake(['test.crossref.org/*' => Http::response('bad request', 400)]);
        $deposit = CrossrefDeposit::create([
            'publication_id' => $this->publication->id,
            'doi' => '10.5555/demo.v12.i3.a1',
            'status' => 'pending',
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            (new DepositToCrossrefJob($deposit))->handle(app(CrossrefXmlBuilder::class), app(CrossrefDoiMinter::class));
        } finally {
            $fresh = $deposit->fresh();
            $this->assertSame('failed', $fresh->status);
            $this->assertNotNull($fresh->last_error);
        }
    }

    public function test_status_reports_the_local_tracking_record(): void
    {
        CrossrefDeposit::create([
            'publication_id' => $this->publication->id,
            'doi' => '10.5555/demo.v12.i3.a1',
            'status' => 'submitted',
        ]);

        $receipt = app(CrossrefDoiMinter::class)->status('10.5555/demo.v12.i3.a1');

        $this->assertSame('submitted', $receipt->status);
    }

    public function test_status_for_an_unknown_doi_is_unknown(): void
    {
        $receipt = app(CrossrefDoiMinter::class)->status('10.0000/not-real');

        $this->assertSame('unknown', $receipt->status);
    }
}
