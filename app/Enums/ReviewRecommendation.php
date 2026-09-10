<?php

namespace Alpha\Journal\Enums;

/**
 * Reviewer recommendations (§3) — advisory to the editor, never drives the submission stage
 * directly; only an EditorialDecision does that.
 */
enum ReviewRecommendation: string
{
    case Accept = 'accept';
    case RevisionsRequired = 'revisions_required';
    case ResubmitForReview = 'resubmit_for_review';
    case ResubmitElsewhere = 'resubmit_elsewhere';
    case Decline = 'decline';
    case SeeComments = 'see_comments';

    public function label(): string
    {
        return match ($this) {
            self::Accept => 'Accept',
            self::RevisionsRequired => 'Revisions required',
            self::ResubmitForReview => 'Resubmit for review',
            self::ResubmitElsewhere => 'Resubmit elsewhere',
            self::Decline => 'Decline',
            self::SeeComments => 'See comments',
        };
    }
}
