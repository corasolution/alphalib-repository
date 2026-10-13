<?php

namespace Alpha\Journal\Enums;

/**
 * Editor decisions (§3): send_to_screening / send_to_review / return_to_author / accept /
 * request_revisions / resubmit / decline / send_to_copyediting / send_to_production / publish.
 */
enum EditorialDecisionType: string
{
    case SendToScreening = 'send_to_screening';
    case SendToReview = 'send_to_review';

    /**
     * Back to the author without rejecting it — the "Return to Author" box that appears four
     * times in a typical published workflow (bad formatting at screening, missing data at
     * verification, not recommended after review). Before this existed an editor looking at a
     * badly formatted manuscript had to choose between pushing it into peer review and rejecting
     * it outright, which is why journals end up handling it over email instead.
     */
    case ReturnToAuthor = 'return_to_author';
    case Accept = 'accept';
    case RequestRevisions = 'request_revisions';
    case Resubmit = 'resubmit';
    case Decline = 'decline';
    case SendToCopyediting = 'send_to_copyediting';
    case SendToProduction = 'send_to_production';
    case Publish = 'publish';

    /**
     * Publish is a separate, higher-privilege action from accept (§4.1) — Editor or Journal
     * Manager only, never Section Editor.
     */
    /** Leaves the submission where it is; only its custody changes. */
    public function keepsStage(): bool
    {
        return $this === self::ReturnToAuthor;
    }

    public function requiresPublishAuthority(): bool
    {
        return $this === self::Publish;
    }

    public function label(): string
    {
        return match ($this) {
            self::SendToScreening => 'Sent to screening',
            self::SendToReview => 'Sent to review',
            self::ReturnToAuthor => 'Returned to the author',
            self::Accept => 'Accepted',
            self::RequestRevisions => 'Revisions requested',
            self::Resubmit => 'Resubmitted for review',
            self::Decline => 'Declined',
            self::SendToCopyediting => 'Sent to copyediting',
            self::SendToProduction => 'Sent to production',
            self::Publish => 'Published',
        };
    }
}
