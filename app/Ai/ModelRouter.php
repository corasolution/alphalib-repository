<?php

namespace Alpha\Journal\Ai;

use Alpha\Journal\Enums\AiFeature;

/**
 * Feature -> model, per CLAUDE.md §6 guardrail 5. Config-driven so a model can be swapped without
 * touching a feature class, and so the cheap/expensive split stays visible in one file rather than
 * scattered across call sites.
 */
class ModelRouter
{
    public function modelFor(AiFeature $feature): string
    {
        return config("ai.models.{$feature->value}") ?? config('ai.default_model');
    }

    /**
     * Cost in microcents. An unpriced model returns 0 rather than throwing — failing to price a
     * run is not a good reason to fail the run itself, and a 0 in the log is visibly wrong.
     */
    public function costMicrocents(string $model, int $inputTokens, int $outputTokens): int
    {
        // Indexed off the loaded array, NOT config("ai.pricing.{$model}"): model ids contain
        // dots (gemini-2.5-flash), and config() treats those as nested-key separators — so the
        // dotted lookup silently missed and every Gemini run reported a cost of zero.
        $pricing = config('ai.pricing')[$model] ?? null;

        if (! $pricing) {
            return 0;
        }

        return (int) round($inputTokens * $pricing['input'] + $outputTokens * $pricing['output']);
    }
}
