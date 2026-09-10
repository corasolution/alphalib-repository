<?php

namespace Alpha\Journal\Notifications\Telegram;

use Illuminate\Notifications\Notification;

/**
 * Laravel notification channel for Telegram. Registered in AppServiceProvider under the name
 * 'telegram', which is what NotificationPreferences returns from via().
 */
class TelegramChannel
{
    public function __construct(private readonly TelegramBotClient $client) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $chatId = $notifiable->telegram_chat_id ?? null;

        // Belt and braces: NotificationPreferences already refuses this channel for an unlinked
        // user, but a notification could route here directly and a null chat_id would otherwise
        // become a confusing 400 from Telegram rather than a no-op.
        if (blank($chatId) || ! method_exists($notification, 'toTelegram')) {
            return;
        }

        $text = $notification->toTelegram($notifiable);

        if (blank($text)) {
            return;
        }

        $this->client->sendMessage((string) $chatId, $text);
    }
}
