<?php

namespace Alpha\Journal\Enums;

enum AiRunStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * The journal's per-journal master switch was off (CLAUDE.md §6 guardrail 2). Recorded rather
     * than silently skipped: "we declined to process this manuscript" is exactly the kind of thing
     * a university's data-protection review asks to see evidence of.
     */
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
            self::Blocked => 'Blocked by policy',
        };
    }
}
