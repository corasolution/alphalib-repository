<?php

namespace Alpha\Journal\Enums;

/**
 * The AI features of CLAUDE.md §6. The value is the storage key in three places at once —
 * `ai_runs.feature`, the model-routing map in config/ai.php, and the prompt directory name under
 * resources/ai/prompts — so they can never drift apart into a silent mismatch.
 */
enum AiFeature: string
{
    case MetadataExtraction = 'metadata_extraction';

    public function label(): string
    {
        return match ($this) {
            self::MetadataExtraction => 'Metadata extraction',
        };
    }
}
