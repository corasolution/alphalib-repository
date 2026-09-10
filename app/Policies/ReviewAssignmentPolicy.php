<?php

namespace Alpha\Journal\Policies;

use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Models\User;

/**
 * The reviewer-facing review-submission page (§13.7 continued — after accept/decline, the
 * reviewer needs somewhere to actually read the manuscript and submit). Ownership only: a
 * ReviewAssignment is not reachable through SubmissionPolicy::view at all, on purpose — a
 * reviewer is not stage-assigned, and must never see the full submission workspace (people,
 * decisions, other reviewers) the way an editor or the author can (CLAUDE.md §4 anonymity
 * boundary).
 */
class ReviewAssignmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_site_admin ? true : null;
    }

    public function view(User $user, ReviewAssignment $reviewAssignment): bool
    {
        return $reviewAssignment->reviewer_id === $user->id;
    }
}
