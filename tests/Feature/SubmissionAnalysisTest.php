<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\AiRunStatus;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Models\AiRun;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\JournalSetting;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\ManuscriptFixtures;
use Tests\TestCase;

/**
 * A1 pre-fill end to end (CLAUDE.md §6, §13.1). The through-line of every test here is that this
 * endpoint is *advisory*: it may fail, be switched off, or be handed a file it cannot read, and in
 * every one of those cases the author must still be able to submit exactly as they do today.
 */
class SubmissionAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $author;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo']]);
        $this->author = User::factory()->create();
        $this->journal->grantRole($this->author, JournalRole::Author);

        config()->set('services.anthropic.key', 'test-key');
        config()->set('ai.enabled', true);

        $this->dir = sys_get_temp_dir().'/alpha-analysis-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function enableAi(bool $enabled = true): void
    {
        JournalSetting::updateOrCreate(
            ['journal_id' => $this->journal->id, 'name' => 'ai_enabled', 'locale' => ''],
            ['value' => $enabled],
        );
    }

    private function manuscript(string $name = 'paper.docx'): UploadedFile
    {
        $path = ManuscriptFixtures::docx($this->dir.'/'.$name, [
            'Coral Bleaching in the Gulf of Thailand',
            'Sophea Chan, Dara Sok',
            'Abstract',
            'We surveyed reef sites across three seasons.',
        ]);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function fakeExtraction(array $input = []): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'extracted_metadata',
                    'input' => array_merge([
                        'title' => 'Coral Bleaching in the Gulf of Thailand',
                        'abstract' => 'We surveyed reef sites across three seasons.',
                        'keywords' => ['coral', 'bleaching'],
                        'authors' => [
                            ['given_name' => 'Sophea', 'family_name' => 'Chan', 'affiliation' => 'RUPP'],
                        ],
                        'language' => 'en',
                        'confidence' => 'high',
                    ], $input),
                ]],
                'usage' => ['input_tokens' => 3200, 'output_tokens' => 210],
            ]),
        ]);
    }

    private function analyze(?UploadedFile $file = null)
    {
        return $this->actingAs($this->author)->post(
            route('journal.submissions.analyze', ['journal' => $this->journal->path]),
            ['manuscript' => $file ?? $this->manuscript()],
        );
    }

    public function test_it_returns_metadata_for_the_author_to_confirm(): void
    {
        $this->enableAi();
        $this->fakeExtraction();

        $response = $this->analyze();

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('metadata.title', 'Coral Bleaching in the Gulf of Thailand');
        $response->assertJsonPath('metadata.authors.0.family_name', 'Chan');
        $response->assertJsonPath('metadata.keywords.0', 'coral');
    }

    public function test_analysis_creates_no_submission(): void
    {
        $this->enableAi();
        $this->fakeExtraction();

        $this->analyze();

        // The whole safety argument for this endpoint: it proposes, it does not act. If it ever
        // starts creating submissions, a failed extraction becomes a half-made submission in an
        // editor's queue.
        $this->assertSame(0, Submission::count());
    }

    public function test_a_journal_that_has_not_opted_in_gets_no_analysis_and_sends_nothing(): void
    {
        Http::fake();

        $response = $this->analyze();

        $response->assertOk();
        $response->assertJsonPath('status', 'disabled');
        Http::assertNothingSent();
    }

    /**
     * The case that was actually happening on this install: AI switched on, no API key.
     *
     * The endpoint answered "enabled", so every manuscript an author picked spent a round trip to
     * fail, wrote a failed row to ai_runs, and told them "we couldn't read this manuscript
     * automatically" — blaming a perfectly good PDF for a missing credential.
     */
    public function test_ai_switched_on_without_a_credential_looks_like_ai_switched_off(): void
    {
        $this->enableAi();
        config()->set('services.anthropic.key', null);
        Http::fake();

        $response = $this->analyze();

        $response->assertOk();
        $response->assertJsonPath('status', 'disabled');
        Http::assertNothingSent();
        $this->assertSame(0, AiRun::count(), 'A missing key must not accumulate failed runs.');
    }

    public function test_a_format_we_cannot_read_is_reported_as_unsupported_not_an_error(): void
    {
        $this->enableAi();
        Http::fake();
        $path = ManuscriptFixtures::odt($this->dir.'/paper.odt');

        $response = $this->analyze(new UploadedFile($path, 'paper.odt', null, null, true));

        // .odt and legacy .doc remain perfectly valid submissions — they just cannot be
        // pre-filled from. The author types the metadata, exactly as they do today.
        $response->assertOk();
        $response->assertJsonPath('status', 'unsupported');

        // And crucially, we established that without spending a token finding out.
        Http::assertNothingSent();
    }

    public function test_an_unreadable_pdf_degrades_to_a_message_rather_than_a_500(): void
    {
        $this->enableAi();
        Http::fake();
        $path = ManuscriptFixtures::unreadablePdf($this->dir.'/scan.pdf');

        $response = $this->analyze(new UploadedFile($path, 'scan.pdf', null, null, true));

        // Scanned manuscripts are common in the region. The author must be told why nothing was
        // filled in, and then be able to carry on typing it themselves.
        $response->assertOk();
        $response->assertJsonPath('status', 'unreadable');
    }

    public function test_an_upstream_failure_degrades_rather_than_breaking_the_form(): void
    {
        $this->enableAi();
        Http::fake(['api.anthropic.com/*' => Http::response('nope', 500)]);

        $response = $this->analyze();

        $response->assertOk();
        $response->assertJsonPath('status', 'failed');
        $this->assertSame(AiRunStatus::Failed, AiRun::withoutGlobalScopes()->sole()->status);
    }

    public function test_the_run_is_attributed_to_the_journal_that_paid_for_it(): void
    {
        $this->enableAi();
        $this->fakeExtraction();

        $this->analyze();

        $run = AiRun::withoutGlobalScopes()->sole();
        $this->assertSame($this->journal->id, $run->journal_id);
        $this->assertSame(3200, $run->input_tokens);
    }

    public function test_someone_who_cannot_submit_here_cannot_spend_the_journals_ai_budget(): void
    {
        $stranger = User::factory()->create();
        $this->enableAi();
        Http::fake();

        $this->actingAs($stranger)->post(
            route('journal.submissions.analyze', ['journal' => $this->journal->path]),
            ['manuscript' => $this->manuscript()],
        )->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_a_guest_cannot_reach_the_endpoint(): void
    {
        Http::fake();

        $this->post(
            route('journal.submissions.analyze', ['journal' => $this->journal->path]),
            ['manuscript' => $this->manuscript()],
        )->assertRedirect(route('login'));

        Http::assertNothingSent();
    }

    public function test_feedback_records_whether_the_author_kept_the_suggestion(): void
    {
        $this->enableAi();
        $this->fakeExtraction();

        $runId = $this->analyze()->json('run_id');

        $this->actingAs($this->author)->post(
            route('journal.submissions.analyze.feedback', ['journal' => $this->journal->path]),
            ['run_id' => $runId, 'accepted' => true],
        )->assertOk();

        $this->assertNotNull(AiRun::withoutGlobalScopes()->find($runId)->accepted_at);
    }

    public function test_feedback_cannot_stamp_a_verdict_on_another_journals_run(): void
    {
        $other = Journal::create([
            'site_id' => $this->journal->site_id, 'path' => 'other', 'title' => ['en' => 'Other'],
        ]);
        $foreignRun = AiRun::withoutGlobalScopes()->create([
            'journal_id' => $other->id,
            'feature' => 'metadata_extraction',
            'model' => 'claude-haiku-4-5-20251001',
            'prompt_version' => 'v1',
            'status' => AiRunStatus::Succeeded->value,
        ]);

        $this->actingAs($this->author)->post(
            route('journal.submissions.analyze.feedback', ['journal' => $this->journal->path]),
            ['run_id' => $foreignRun->id, 'accepted' => true],
        )->assertOk();

        // Silently ignored rather than 404'd — but the crucial part is that it was not written.
        $this->assertNull(AiRun::withoutGlobalScopes()->find($foreignRun->id)->accepted_at);
    }

    public function test_an_oversized_or_missing_file_is_rejected_by_validation(): void
    {
        $this->enableAi();
        Http::fake();

        $this->actingAs($this->author)->postJson(
            route('journal.submissions.analyze', ['journal' => $this->journal->path]),
            [],
        )->assertStatus(422);

        Http::assertNothingSent();
    }
}
