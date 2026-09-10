<?php

namespace Alpha\Journal\Listeners;

use Alpha\Journal\Events\SubmissionStageChanged;
use Alpha\Journal\Mail\EmailTemplateRenderer;
use Alpha\Journal\Notifications\TemplatedNotification;
use Alpha\Journal\Support\Activity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Notifications subscribe to workflow events; they never call SubmissionWorkflow directly
 * (CLAUDE.md §3). All outbound mail is logged against the submission and visible in the audit
 * trail (§11.1).
 */
class SendDecisionNotification implements ShouldQueue
{
    /** @var array<string, string> decision value => template key. No entry = no author-facing email for that decision. */
    private const TEMPLATE_KEYS = [
        'send_to_review' => 'submission.send_to_review',
        'accept' => 'submission.accepted',
        'send_to_copyediting' => 'submission.accepted',
        'request_revisions' => 'submission.revisions_requested',
        'resubmit' => 'submission.revisions_requested',
        'decline' => 'submission.declined',
        'publish' => 'submission.published',
    ];

    public function __construct(private readonly EmailTemplateRenderer $renderer) {}

    public function handle(SubmissionStageChanged $event): void
    {
        $key = self::TEMPLATE_KEYS[$event->decision->value] ?? null;

        if ($key === null) {
            return;
        }

        $submission = $event->submission;
        $journal = $submission->journal;
        $author = $submission->submittedBy;
        $locale = $journal->primary_locale;

        $variables = [
            'journal_title' => $journal->title->get($locale, 'en') ?? $journal->path,
            'author_name' => $author->name,
            'submission_title' => $submission->currentPublication?->title?->get($locale, 'en') ?? "Submission #{$submission->id}",
            'decision_reason' => $event->decisionRecord->reason ?? '',
        ];

        $rendered = $this->renderer->render($journal, $key, $locale, $variables);

        if ($rendered === null) {
            return;
        }

        Notification::send($author, new TemplatedNotification($rendered, $key));

        Activity::log('mail.sent', $submission, $rendered->subject, [
            'template_key' => $key,
            'recipient' => $author->email,
        ]);
    }
}
