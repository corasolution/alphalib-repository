<?php

namespace Alpha\Journal\Reviewers;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\ReviewType;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Models\User;

/**
 * The one place reviewer-identity visibility is decided (CLAUDE.md §4: "Anonymity is a security
 * boundary... Every leak here is a reputational incident for a customer"). Every caller that
 * might expose a reviewer to a submission-facing payload — ReviewAssignmentResource, the
 * submission workspace page, anything added later — must go through this, never re-derive the
 * rule itself.
 */
class ReviewerIdentityVisibility
{
    public static function canSee(?User $viewer, ReviewAssignment $assignment): bool
    {
        if ($viewer === null) {
            return false;
        }

        if ($viewer->id === $assignment->reviewer_id) {
            return true;
        }

        if ($viewer->is_site_admin) {
            return true;
        }

        $journal = $assignment->reviewRound->submission->journal;

        if ($viewer->hasRoleOn($journal, JournalRole::Editor)) {
            return true;
        }

        return $assignment->reviewRound->submission->review_type === ReviewType::Open;
    }
}
