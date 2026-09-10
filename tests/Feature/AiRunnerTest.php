<?php

namespace Tests\Feature;

use Alpha\Journal\Ai\AiRunner;
use Alpha\Journal\Enums\AiFeature;
use Alpha\Journal\Enums\AiRunStatus;
use Alpha\Journal\Models\AiRun;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\JournalSetting;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The choke point every AI feature funnels through (CLAUDE.md §6). These tests are about the
 * three things it owes the rest of the system — the kill switch, the audit row, and honest
 * accounting — rather than about any one feature.
 */
class AiRunnerTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo']]);

        config()->set('services.anthropic.key', 'test-key');
        config()->set('ai.enabled', true);
    }

    private function enableAi(Journal $journal, bool $enabled = true): void
    {
        JournalSetting::updateOrCreate(
            ['journal_id' => $journal->id, 'name' => 'ai_enabled', 'locale' => ''],
            ['value' => $enabled],
        );
    }

    private function fakeSuccess(int $inputTokens = 4000, int $outputTokens = 300): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'extracted_metadata',
                    'input' => ['title' => 'Coral Bleaching', 'confidence' => 'high'],
                ]],
                'usage' => ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens],
            ]),
        ]);
    }

    /** @return array{run: AiRun, data: array<string, mixed>|null} */
    private function runFeature(string $content = 'Some manuscript text'): array
    {
        return app(AiRunner::class)->run(
            AiFeature::MetadataExtraction,
            $this->journal,
            $content,
            'extracted_metadata',
            ['type' => 'object'],
        );
    }

    public function test_a_journal_that_has_not_opted_in_is_never_processed(): void
    {
        Http::fake();

        // No ai_enabled setting at all — the default state of every journal.
        $result = $this->runFeature();

        // The load-bearing assertion: the manuscript text never left the building.
        Http::assertNothingSent();
        $this->assertNull($result['data']);
        $this->assertSame(AiRunStatus::Blocked, $result['run']->status);
    }

    public function test_an_explicitly_disabled_journal_is_never_processed(): void
    {
        Http::fake();
        $this->enableAi($this->journal, false);

        $this->runFeature();

        Http::assertNothingSent();
    }

    public function test_the_installation_wide_switch_overrides_a_journals_opt_in(): void
    {
        Http::fake();
        $this->enableAi($this->journal);
        config()->set('ai.enabled', false);

        $this->runFeature();

        Http::assertNothingSent();
    }

    public function test_a_block_is_recorded_rather_than_silently_skipped(): void
    {
        Http::fake();

        $this->runFeature();

        // "We declined to process this manuscript" is exactly what a data-protection review
        // asks to see evidence of — a silent skip leaves no such evidence.
        $run = AiRun::sole();
        $this->assertSame(AiRunStatus::Blocked, $run->status);
        $this->assertSame(0, $run->input_tokens);
    }

    public function test_a_successful_run_is_logged_with_tokens_cost_and_prompt_version(): void
    {
        $this->enableAi($this->journal);
        $this->fakeSuccess(inputTokens: 4000, outputTokens: 300);

        $result = $this->runFeature();

        $run = AiRun::sole();
        $this->assertSame(AiRunStatus::Succeeded, $run->status);
        $this->assertSame(4000, $run->input_tokens);
        $this->assertSame(300, $run->output_tokens);
        $this->assertSame('v1', $run->prompt_version);
        $this->assertSame(AiFeature::MetadataExtraction, $run->feature);
        $this->assertSame('Coral Bleaching', $run->output['title']);
        $this->assertSame('Coral Bleaching', $result['data']['title']);

        // Haiku at 1/5 microcents per token: 4000*1 + 300*5 = 5,500 microcents = 0.55 cents.
        // Pinned deliberately — this is the assertion that catches a factor-of-100 slip in the
        // pricing table, which is otherwise invisible until someone reads a billing report.
        $this->assertSame(5500, $run->cost_microcents);
        $this->assertSame(0.55, $run->costInCents());
    }

    public function test_a_failed_call_is_logged_as_faithfully_as_a_successful_one(): void
    {
        $this->enableAi($this->journal);
        Http::fake(['api.anthropic.com/*' => Http::response('upstream exploded', 500)]);

        $result = $this->runFeature();

        // A failed run is still a run that touched a manuscript. If failures went unlogged, the
        // audit trail would quietly under-report what was sent.
        $run = AiRun::sole();
        $this->assertSame(AiRunStatus::Failed, $run->status);
        $this->assertNotNull($run->error);
        $this->assertNull($result['data']);
    }

    public function test_a_model_answering_without_the_tool_is_a_failure_not_an_empty_success(): void
    {
        $this->enableAi($this->journal);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Sure! Here is the metadata...']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
        ]);

        $result = $this->runFeature();

        // Otherwise a chatty response silently becomes "we extracted nothing", which looks
        // identical to a manuscript that genuinely had no metadata.
        $this->assertSame(AiRunStatus::Failed, AiRun::sole()->status);
        $this->assertNull($result['data']);
    }

    public function test_the_manuscript_is_truncated_before_it_is_sent(): void
    {
        $this->enableAi($this->journal);
        config()->set('ai.max_input_chars', 100);
        $this->fakeSuccess();

        $this->runFeature(str_repeat('x', 5000));

        // Truncation lives in the runner, not each feature, so no feature can forget it and mail
        // a 200-page manuscript to a per-submission model.
        Http::assertSent(fn ($request) => strlen($request['messages'][0]['content']) <= 100);
    }

    public function test_one_journals_opt_in_does_not_enable_another(): void
    {
        $other = Journal::create([
            'site_id' => $this->journal->site_id, 'path' => 'other', 'title' => ['en' => 'Other'],
        ]);
        $this->enableAi($this->journal);

        $this->assertTrue(app(AiRunner::class)->isEnabledFor($this->journal));
        $this->assertFalse(app(AiRunner::class)->isEnabledFor($other));
    }

    public function test_accepting_a_run_records_who_judged_it(): void
    {
        $this->enableAi($this->journal);
        $this->fakeSuccess();
        $user = User::factory()->create();

        $run = $this->runFeature()['run'];
        $run->markReviewed(true, $user);

        $this->assertNotNull($run->fresh()->accepted_at);
        $this->assertNull($run->fresh()->rejected_at);
        $this->assertSame($user->id, $run->fresh()->reviewed_by);
    }

    public function test_a_run_judged_twice_never_reads_as_both_accepted_and_rejected(): void
    {
        $this->enableAi($this->journal);
        $this->fakeSuccess();

        $run = $this->runFeature()['run'];
        $run->markReviewed(true);
        $run->markReviewed(false);

        $this->assertNull($run->fresh()->accepted_at);
        $this->assertNotNull($run->fresh()->rejected_at);
    }
}
