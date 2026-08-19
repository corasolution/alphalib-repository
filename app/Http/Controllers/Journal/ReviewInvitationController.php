<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Support\Activity;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * No `auth` middleware on these two routes — the signed URL itself is the credential (CLAUDE.md
 * §13.7). Signature validation happens in the `signed` route middleware before either action runs.
 */
class ReviewInvitationController extends Controller
{
    public function accept(string $journal, ReviewAssignment $reviewAssignment): JsonResponse
    {
        try {
            $reviewAssignment->acceptInvitation();
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        Activity::log('review.accepted', $reviewAssignment->reviewRound->submission, null, [
            'review_assignment_id' => $reviewAssignment->id,
        ]);

        return response()->json(['status' => 'accepted']);
    }

    public function decline(string $journal, ReviewAssignment $reviewAssignment): JsonResponse
    {
        try {
            $reviewAssignment->declineInvitation();
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        Activity::log('review.declined', $reviewAssignment->reviewRound->submission, null, [
            'review_assignment_id' => $reviewAssignment->id,
        ]);

        return response()->json(['status' => 'declined']);
    }
}
