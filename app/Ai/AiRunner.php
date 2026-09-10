<?php

namespace Alpha\Journal\Ai;

use Alpha\Journal\Ai\Providers\ProviderRegistry;
use Alpha\Journal\Enums\AiFeature;
use Alpha\Journal\Enums\AiRunStatus;
use Alpha\Journal\Models\AiRun;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Throwable;

/**
 * The single choke point every AI feature goes through (CLAUDE.md §6). Features never touch
 * a provider directly, because everything that makes AI safe here lives in this one method:
 * the kill switch, the AiContext mark, and the ai_runs log entry.
 *
 * If a future feature bypasses this class, it loses all three at once — that is the reason for
 * the funnel, not tidiness.
 */
class AiRunner
{
    public function __construct(
        private readonly PromptRegistry $prompts,
        private readonly ModelRouter $router,
        private readonly ProviderRegistry $providers,
        private readonly AiContext $context,
    ) {}

    /**
     * @param  array<string, mixed>  $schema
     * @return array{run: AiRun, data: array<string, mixed>|null}
     */
    public function run(
        AiFeature $feature,
        Journal $journal,
        string $userContent,
        string $toolName,
        array $schema,
        ?Submission $submission = null,
    ): array {
        $prompt = $this->prompts->resolve($feature);
        $model = $this->router->modelFor($feature);

        if (! $this->isEnabledFor($journal)) {
            // Logged, not silently skipped. "We declined to process this manuscript" is precisely
            // what a data-protection review asks for evidence of.
            return [
                'run' => $this->log($feature, $journal, $submission, $model, $prompt['version'], [
                    'status' => AiRunStatus::Blocked,
                    'error' => 'AI processing is disabled for this journal.',
                ]),
                'data' => null,
            ];
        }

        // Truncation happens here rather than in each feature so no feature can forget it and
        // send a 200-page manuscript to a per-submission model.
        $content = mb_substr($userContent, 0, (int) config('ai.max_input_chars'));

        $startedAt = microtime(true);

        try {
            $result = $this->context->during(
                fn () => $this->providers->forModel($model)->structured($model, $prompt['body'], $content, $toolName, $schema)
            );
        } catch (Throwable $e) {
            // A failed run is still a run that touched a manuscript, so it is logged with the same
            // fidelity as a successful one — including the latency that was actually spent.
            return [
                'run' => $this->log($feature, $journal, $submission, $model, $prompt['version'], [
                    'status' => AiRunStatus::Failed,
                    'error' => mb_substr($e->getMessage(), 0, 2000),
                    'latency_ms' => $this->elapsedMs($startedAt),
                ]),
                'data' => null,
            ];
        }

        $run = $this->log($feature, $journal, $submission, $model, $prompt['version'], [
            'status' => AiRunStatus::Succeeded,
            'output' => $result['data'],
            'input_tokens' => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
            'cost_microcents' => $this->router->costMicrocents($model, $result['input_tokens'], $result['output_tokens']),
            'latency_ms' => $this->elapsedMs($startedAt),
        ]);

        return ['run' => $run, 'data' => $result['data']];
    }

    /**
     * Whether a feature can actually run — both switches on AND a usable credential for the model
     * that would serve it.
     *
     * isEnabledFor() answers "is this permitted", which is not the same question. An installation
     * with AI switched on but no API key answered yes, so picking a manuscript spent a round trip
     * to fail, logged a failed run, and told the author "we couldn't read this manuscript" —
     * blaming their file for a missing key. Callers that degrade gracefully want this one.
     */
    public function isAvailableFor(Journal $journal, AiFeature $feature): bool
    {
        if (! $this->isEnabledFor($journal)) {
            return false;
        }

        try {
            return $this->providers->forModel($this->router->modelFor($feature))->isConfigured();
        } catch (Throwable) {
            // An unroutable model id is a misconfiguration, not something to surface to an author.
            return false;
        }
    }

    /**
     * Both switches must be on: the installation-wide one and the journal's own. The journal's
     * default comes from config and ships OFF — unpublished manuscripts are confidential, so a
     * journal nobody has configured is a journal that has not consented (§6 guardrail 2).
     */
    public function isEnabledFor(Journal $journal): bool
    {
        if (! $this->installationEnabled()) {
            return false;
        }

        return (bool) $journal->setting('ai_enabled', config('ai.journal_default'));
    }

    /**
     * The installation-wide switch: the admin-set value when one exists, else the config
     * default. Nullable in the database on purpose, so "never configured" stays distinguishable
     * from "explicitly switched off" and config keeps governing until someone decides.
     */
    public function installationEnabled(): bool
    {
        $override = Site::first()?->ai_enabled;

        return $override ?? (bool) config('ai.enabled');
    }

    /** @param array<string, mixed> $attributes */
    private function log(
        AiFeature $feature,
        Journal $journal,
        ?Submission $submission,
        string $model,
        string $promptVersion,
        array $attributes,
    ): AiRun {
        return AiRun::create(array_merge([
            'journal_id' => $journal->id,
            'submission_id' => $submission?->id,
            'feature' => $feature->value,
            'model' => $model,
            'prompt_version' => $promptVersion,
        ], $attributes));
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
