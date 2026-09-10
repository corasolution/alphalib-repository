<?php

namespace Alpha\Journal\Console\Commands;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Mail\EmailTemplateRenderer;
use Alpha\Journal\Models\ActivityLog;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Notifications\TemplatedNotification;
use Alpha\Journal\Support\Activity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * "Unclaimed submission alert (no editor assigned after N days)" (§11.2). Re-alerts daily (via
 * an activity_log lookup, not a dedicated column) until an editor is assigned — unlike the
 * reviewer reminders, there's no natural "stop after N attempts" here.
 */
class AlertUnclaimedSubmissions extends Command
{
    protected $signature = 'journal:alert-unclaimed-submissions';

    protected $description = 'Notify editors when a submission has no assigned editor after the configured grace period.';

    public function handle(EmailTemplateRenderer $renderer): int
    {
        $alerted = 0;

        Submission::where('stage', SubmissionStage::Submission->value)
            ->with('journal')
            ->chunkById(100, function ($submissions) use ($renderer, &$alerted) {
                foreach ($submissions as $submission) {
                    if ($this->alertOnSubmission($submission, $renderer)) {
                        $alerted++;
                    }
                }
            });

        $this->info("Alerted on {$alerted} unclaimed submission(s).");

        return self::SUCCESS;
    }

    /**
     * Named alertOnSubmission(), not alert() — the latter collides with the base Command
     * class's public alert() output helper.
     */
    private function alertOnSubmission(Submission $submission, EmailTemplateRenderer $renderer): bool
    {
        $journal = $submission->journal;
        $unclaimedDays = (int) $journal->setting('unclaimed_submission_days', 5);
        $referenceDate = $submission->submitted_at ?? $submission->created_at;

        if (! $referenceDate->lessThanOrEqualTo(now()->subDays($unclaimedDays))) {
            return false;
        }

        $hasEditor = $submission->stageAssignments()
            ->whereIn('role', [JournalRole::Editor->value, JournalRole::SectionEditor->value])
            ->exists();

        if ($hasEditor) {
            return false;
        }

        $alertedToday = ActivityLog::where('subject_type', $submission->getMorphClass())
            ->where('subject_id', $submission->id)
            ->where('action', 'submission.unclaimed_alert')
            ->whereDate('created_at', today())
            ->exists();

        if ($alertedToday) {
            return false;
        }

        $editors = $journal->usersWithRole(JournalRole::Editor)->get();

        if ($editors->isEmpty()) {
            return false;
        }

        $locale = $journal->primary_locale;
        $submissionTitle = $submission->currentPublication?->title?->get($locale, 'en') ?? "Submission #{$submission->id}";

        foreach ($editors as $editor) {
            $rendered = $renderer->render($journal, 'submission.unclaimed_alert', $locale, [
                'journal_title' => $journal->title->get($locale, 'en') ?? $journal->path,
                'editor_name' => $editor->name,
                'submission_title' => $submissionTitle,
                'submitted_date' => $referenceDate->toFormattedDateString(),
            ]);

            if ($rendered !== null) {
                Notification::send($editor, new TemplatedNotification($rendered, 'submission.unclaimed_alert'));
            }
        }

        Activity::log('submission.unclaimed_alert', $submission, null, ['days_unclaimed' => $unclaimedDays]);

        return true;
    }
}
