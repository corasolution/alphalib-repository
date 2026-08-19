<?php

namespace Alpha\Journal\Enums;

/**
 * SUBMISSION -> REVIEW -> COPYEDITING -> PRODUCTION -> PUBLISHED, DECLINED from any stage (§3).
 */
enum SubmissionStage: string
{
    case Submission = 'submission';
    case Review = 'review';
    case Copyediting = 'copyediting';
    case Production = 'production';
    case Published = 'published';
    case Declined = 'declined';

    public function isTerminal(): bool
    {
        return $this === self::Published || $this === self::Declined;
    }

    public function label(): string
    {
        return match ($this) {
            self::Submission => 'Submission',
            self::Review => 'Review',
            self::Copyediting => 'Copyediting',
            self::Production => 'Production',
            self::Published => 'Published',
            self::Declined => 'Declined',
        };
    }
}
