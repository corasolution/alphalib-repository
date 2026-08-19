<?php

namespace Alpha\Journal\Notifications;

use Alpha\Journal\Mail\RenderedEmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TemplatedEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly RenderedEmailTemplate $template) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->template->subject);

        foreach (explode("\n\n", $this->template->body) as $paragraph) {
            $message->line($paragraph);
        }

        return $message;
    }
}
