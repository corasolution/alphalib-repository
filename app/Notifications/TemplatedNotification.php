<?php

namespace Alpha\Journal\Notifications;

use Alpha\Journal\Mail\RenderedEmailTemplate;
use Alpha\Journal\Models\User;
use Alpha\Journal\Notifications\Telegram\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One rendered template, delivered on whichever channels the recipient has chosen and the
 * installation has configured (CLAUDE.md §11.1).
 *
 * Was TemplatedNotification until Telegram arrived; the name no longer described what it
 * did. Every existing sender keeps working unchanged — the routing decision lives in via(), not
 * at the call sites, so adding a third channel later touches this class only.
 */
class TemplatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly RenderedEmailTemplate $template,
        /** Template key, so a user can mute one kind of message without muting the channel. */
        private readonly ?string $templateKey = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        // On-demand notifications (Notification::route(...)) have no preferences to consult —
        // an emailed invitation to someone with no account still has to go out.
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $channels = app(NotificationPreferences::class)->channelsFor($notifiable, $this->templateKey);

        return array_map(
            fn (string $channel) => $channel === NotificationPreferences::CHANNEL_TELEGRAM
                ? TelegramChannel::class
                : $channel,
            $channels,
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->template->subject);

        foreach (explode("\n\n", $this->template->body) as $paragraph) {
            $message->line($paragraph);
        }

        return $message;
    }

    /**
     * The subject is repeated as a first line because Telegram has no subject field, and a
     * message that opens mid-sentence reads like a fragment on a phone.
     */
    public function toTelegram(object $notifiable): string
    {
        return trim($this->template->subject."\n\n".$this->template->body);
    }

    public function templateKey(): ?string
    {
        return $this->templateKey;
    }
}
