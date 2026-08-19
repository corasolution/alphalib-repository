<?php

namespace Alpha\Journal\Console\Commands;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Mail\EmailTemplateRenderer;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Notifications\TemplatedEmailNotification;
use Alpha\Journal\Support\Activity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * "Overdue review escalation to assigned editor" (§11.2). Fires once per assignment via
 * review_assignments.editor_escalated_at, separately from the reviewer-facing reminders in
 * SendReviewerReminders.
 */
class EscalateOverdueReviews extends Command
{
    protected $signature = 'journal:escalate-overdue-reviews';

    protected $description = 'Notify editors when an assigned review is significantly overdue.';

    public function handle(EmailTemplateRenderer $renderer): int
    {
        $escalated = 0;

        ReviewAssignment::whereNull('completed_at')
            ->whereNull('declined_at')
            ->whereNull('editor_escalated_at')
            ->whereNotNull('due_at')
            ->with(['reviewer', 'reviewRound.submission.journal'])
            ->chunkById(100, function ($assignments) use ($renderer, &$escalated) {
                foreach ($assignments as $assignment) {
                    if ($this->escalate($assignment, $renderer)) {
                        $escalated++;
                    }
                }
            });

        $this->info("Escalated {$escalated} overdue review(s).");

        return self::SUCCESS;
    }

    private function escalate(ReviewAssignment $assignment, EmailTemplateRenderer $renderer): bool
    {
        $submission = $assignment->reviewRound->submission;
        $journal = $submission->journal;
        $escalationDays = (int) $journal->setting('review_overdue_escalation_days', 7);

        if (! $assignment->due_at->lessThanOrEqualTo(now()->subDays($escalationDays))) {
            return false;
        }

        $editors = $submission->stageAssignments()
            ->where('stage', $submission->stage->value)
            ->whereIn('role', [JournalRole::Editor->value, JournalRole::SectionEditor->value])
            ->with('user')
            ->get()
            ->pluck('user')
            ->unique('id');

        if ($editors->isEmpty()) {
            $editors = $journal->usersWithRole(JournalRole::Editor)->get();
        }

        if ($editors->isEmpty()) {
            return false;
        }

        $locale = $journal->primary_locale;
        $submissionTitle = $submission->currentPublication?->title?->get($locale, 'en') ?? "Submission #{$submission->id}";

        foreach ($editors as $editor) {
            $rendered = $renderer->render($journal, 'review.overdue_escalation', $locale, [
                'journal_title' => $journal->title->get($locale, 'en') ?? $journal->path,
                'editor_name' => $editor->name,
                'reviewer_name' => $assignment->reviewer->name,
                'submission_title' => $submissionTitle,
                'due_date' => $assignment->due_at->toFormattedDateString(),
            ]);

            if ($rendered !== null) {
                Notification::send($editor, new TemplatedEmailNotification($rendered));
            }
        }

        $assignment->update(['editor_escalated_at' => now()]);
        Activity::log('review.overdue_escalated', $submission, "Reviewer: {$assignment->reviewer->name}", [
            'review_assignment_id' => $assignment->id,
        ]);

        return true;
    }
}
