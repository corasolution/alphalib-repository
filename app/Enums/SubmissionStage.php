<?php

namespace Alpha\Journal\Enums;

/**
 * SUBMISSION -> [SCREENING] -> REVIEW -> COPYEDITING -> PRODUCTION -> PUBLISHED, DECLINED from
 * any stage (§3).
 *
 * Screening is optional and off unless a journal turns it on. Published journal workflows commonly
 * separate the editor-in-chief's first look and the technical/format check from peer review
 * itself; journals that do not want that distinction never see the stage.
 */
enum SubmissionStage: string
{
    case Submission = 'submission';
    case Screening = 'screening';
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
            self::Screening => 'Screening',
            self::Review => 'Review',
            self::Copyediting => 'Copyediting',
            self::Production => 'Production',
            self::Published => 'Published',
            self::Declined => 'Declined',
        };
    }
}
