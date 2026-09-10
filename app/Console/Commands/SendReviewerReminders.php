<?php

namespace Alpha\Journal\Console\Commands;

use Alpha\Journal\Mail\EmailTemplateRenderer;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Notifications\TemplatedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * "Reviewer reminder before due date (configurable N days) and after (escalating)" (§11.2).
 * Idempotent via review_assignments.last_reminder_sent_at — safe to run on every scheduler tick.
 */
class SendReviewerReminders extends Command
{
    protected $signature = 'journal:send-reviewer-reminders';

    protected $description = 'Remind reviewers of upcoming and overdue review deadlines.';

    public function handle(EmailTemplateRenderer $renderer): int
    {
        $sent = 0;

        ReviewAssignment::whereNull('completed_at')
            ->whereNull('declined_at')
            ->whereNotNull('due_at')
            ->with(['reviewer', 'reviewRound.submission.journal'])
            ->chunkById(100, function ($assignments) use ($renderer, &$sent) {
                foreach ($assignments as $assignment) {
                    if ($this->remind($assignment, $renderer)) {
                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} reviewer reminder(s).");

        return self::SUCCESS;
    }

    private function remind(ReviewAssignment $assignment, EmailTemplateRenderer $renderer): bool
    {
        $journal = $assignment->reviewRound->submission->journal;
        $now = now();
        $daysBefore = (int) $journal->setting('reviewer_reminder_days_before', 3);
        $overdueIntervalDays = (int) $journal->setting('reviewer_reminder_overdue_interval_days', 3);

        $isUpcoming = $assignment->due_at->isFuture()
            && $assignment->due_at->lessThanOrEqualTo($now->clone()->addDays($daysBefore))
            && $assignment->last_reminder_sent_at === null;

        $isOverdue = $assignment->due_at->isPast()
            && ($assignment->last_reminder_sent_at === null
                || $assignment->last_reminder_sent_at->lessThanOrEqualTo($now->clone()->subDays($overdueIntervalDays)));

        if (! $isUpcoming && ! $isOverdue) {
            return false;
        }

        $key = $isUpcoming ? 'review.reminder_upcoming' : 'review.reminder_overdue';
        $locale = $journal->primary_locale;

        $rendered = $renderer->render($journal, $key, $locale, [
            'journal_title' => $journal->title->get($locale, 'en') ?? $journal->path,
            'reviewer_name' => $assignment->reviewer->name,
            'submission_title' => $assignment->reviewRound->submission->currentPublication?->title?->get($locale, 'en')
                ?? "Submission #{$assignment->reviewRound->submission->id}",
            'due_date' => $assignment->due_at->toFormattedDateString(),
        ]);

        if ($rendered === null) {
            return false;
        }

        Notification::send($assignment->reviewer, new TemplatedNotification($rendered, $key));
        $assignment->update(['last_reminder_sent_at' => $now]);

        return true;
    }
}
