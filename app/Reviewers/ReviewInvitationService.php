<?php

namespace Alpha\Journal\Reviewers;

use Alpha\Journal\Mail\EmailTemplateRenderer;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Models\ReviewRound;
use Alpha\Journal\Models\User;
use Alpha\Journal\Notifications\TemplatedEmailNotification;
use Alpha\Journal\Support\Activity;
use DateTimeInterface;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Invites a reviewer and emails signed, expiring accept/decline links — no login required
 * (CLAUDE.md §13.7). The links are valid for the same window as the invitation itself: past that
 * point a reviewer reminder (§11.2) is more appropriate than a stale one-tap link.
 */
class ReviewInvitationService
{
    private const LINK_LIFETIME_DAYS = 14;

    public function __construct(private readonly EmailTemplateRenderer $renderer) {}

    public function invite(ReviewRound $round, User $reviewer, User $invitedBy, ?DateTimeInterface $dueAt = null): ReviewAssignment
    {
        $submission = $round->submission;
        $journal = $submission->journal;

        $assignment = $round->reviewAssignments()->create([
            'reviewer_id' => $reviewer->id,
            'invited_at' => now(),
            'due_at' => $dueAt,
        ]);

        $expiry = now()->addDays(self::LINK_LIFETIME_DAYS);
        $locale = $journal->primary_locale;

        $rendered = $this->renderer->render($journal, 'review.invitation', $locale, [
            'journal_title' => $journal->title->get($locale, 'en') ?? $journal->path,
            'reviewer_name' => $reviewer->name,
            'submission_title' => $submission->currentPublication?->title?->get($locale, 'en')
                ?? "Submission #{$submission->id}",
            'due_date' => $dueAt?->format('F j, Y') ?? 'to be confirmed',
            'accept_url' => URL::temporarySignedRoute(
                'journal.reviews.accept',
                $expiry,
                ['journal' => $journal->path, 'reviewAssignment' => $assignment->id]
            ),
            'decline_url' => URL::temporarySignedRoute(
                'journal.reviews.decline',
                $expiry,
                ['journal' => $journal->path, 'reviewAssignment' => $assignment->id]
            ),
        ]);

        if ($rendered !== null) {
            Notification::send($reviewer, new TemplatedEmailNotification($rendered));
        }

        Activity::log('review.invited', $submission, null, [
            'review_assignment_id' => $assignment->id,
            'reviewer_id' => $reviewer->id,
            'invited_by' => $invitedBy->id,
        ]);

        return $assignment;
    }
}
