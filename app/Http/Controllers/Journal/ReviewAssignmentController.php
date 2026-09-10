<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Reviewers\ReviewInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The editor side of "assign a reviewer" (OJS Review stage) — creates the review round if this
 * is the first invitation, creates the ReviewAssignment, and sends the signed accept/decline
 * invitation email. All of that already lived in ReviewInvitationService; this controller is
 * just the HTTP entry point onto it that was previously missing.
 */
class ReviewAssignmentController extends Controller
{
    public function store(Request $request, string $journal, Submission $submission, ReviewInvitationService $invitations): RedirectResponse
    {
        Gate::authorize('assignReviewer', $submission);

        $data = $request->validate([
            'reviewer_id' => [
                'required',
                'integer',
                Rule::exists('journal_user_roles', 'user_id')->where('journal_id', $submission->journal_id)->where('role', 'reviewer'),
            ],
            'due_at' => ['nullable', 'date', 'after:today'],
        ]);

        $round = $submission->currentReviewRound()
            ?? $submission->reviewRounds()->create(['round_number' => 1]);

        $invitations->invite(
            $round,
            User::findOrFail($data['reviewer_id']),
            $request->user(),
            isset($data['due_at']) ? Carbon::parse($data['due_at']) : null,
        );

        return back()->with('success', 'Reviewer invited.');
    }
}
