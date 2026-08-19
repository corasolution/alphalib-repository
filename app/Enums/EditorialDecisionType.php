<?php

namespace Alpha\Journal\Enums;

/**
 * Editor decisions (§3): send_to_review / accept / request_revisions / resubmit / decline /
 * send_to_copyediting / send_to_production / publish.
 */
enum EditorialDecisionType: string
{
    case SendToReview = 'send_to_review';
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
    public function requiresPublishAuthority(): bool
    {
        return $this === self::Publish;
    }

    public function label(): string
    {
        return match ($this) {
            self::SendToReview => 'Sent to review',
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
