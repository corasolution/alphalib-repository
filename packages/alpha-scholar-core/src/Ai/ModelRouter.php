<?php

namespace Corasoft\AlphaScholar\Ai;

/**
 * Haiku for extraction/classification/triage run on every submission; Sonnet for anything
 * a human reads closely — reviewer rationale, JATS structuring, translation (CLAUDE.md §6.5).
 */
class ModelRouter
{
    /** @var array<string, AiTier> */
    private const FEATURE_TIERS = [
        'metadata_extraction' => AiTier::Haiku,
        'desk_screening' => AiTier::Haiku,
        'integrity_signals' => AiTier::Haiku,
        'reviewer_matching_rationale' => AiTier::Sonnet,
        'production_jats' => AiTier::Sonnet,
        'translation' => AiTier::Sonnet,
        'reviewer_assist' => AiTier::Sonnet,
        'reader_summary' => AiTier::Sonnet,
    ];

    public function tierFor(string $feature): AiTier
    {
        return self::FEATURE_TIERS[$feature] ?? AiTier::Haiku;
    }

    public function modelIdFor(string $feature): string
    {
        return match ($this->tierFor($feature)) {
            AiTier::Haiku => config('services.anthropic.haiku_model', 'claude-haiku-4-5'),
            AiTier::Sonnet => config('services.anthropic.sonnet_model', 'claude-sonnet-5'),
        };
    }
}
