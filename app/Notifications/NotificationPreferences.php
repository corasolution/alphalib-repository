<?php

namespace Alpha\Journal\Notifications;

use Alpha\Journal\Models\User;
use Alpha\Journal\Support\Secrets\IntegrationCredentials;

/**
 * Which channels a given user actually receives a given notification on (CLAUDE.md §11.1).
 *
 * Three things all have to agree before a channel is used: the installation has it configured,
 * the user has it switched on, and the user has not muted that specific template. The order
 * matters — checking configuration first means a site with no Telegram bot never even asks the
 * preference question, so a user's stored "telegram: true" cannot produce a failing send.
 */
class NotificationPreferences
{
    public const CHANNEL_MAIL = 'mail';

    public const CHANNEL_TELEGRAM = 'telegram';

    public function __construct(private readonly IntegrationCredentials $credentials) {}

    /**
     * @return list<string> the notification channels to use for this user and template
     */
    public function channelsFor(User $user, ?string $templateKey = null): array
    {
        $preferences = $user->notification_preferences ?? [];

        if ($templateKey !== null && in_array($templateKey, $preferences['muted'] ?? [], true)) {
            return [];
        }

        $channels = [];

        // Email defaults ON. A journal that stops emailing its reviewers stops functioning, so
        // silence has to be something a user chose, never something they inherited.
        if (($preferences[self::CHANNEL_MAIL] ?? true) === true) {
            $channels[] = self::CHANNEL_MAIL;
        }

        if ($this->telegramAvailableFor($user) && ($preferences[self::CHANNEL_TELEGRAM] ?? true) === true) {
            $channels[] = self::CHANNEL_TELEGRAM;
        }

        return $channels;
    }

    /**
     * Telegram needs both a bot to send from and a chat to send to. Defaulting the preference to
     * ON is safe precisely because this gate exists: a user who has never linked receives nothing
     * on Telegram regardless of what their preferences say.
     */
    public function telegramAvailableFor(User $user): bool
    {
        return filled($user->telegram_chat_id)
            && $this->credentials->has(IntegrationCredentials::TELEGRAM_BOT_TOKEN);
    }

    /** @return array<string, mixed> the shape the settings screen renders */
    public function forDisplay(User $user): array
    {
        $preferences = $user->notification_preferences ?? [];

        return [
            'mail' => ($preferences[self::CHANNEL_MAIL] ?? true) === true,
            'telegram' => ($preferences[self::CHANNEL_TELEGRAM] ?? true) === true,
            'muted' => array_values($preferences['muted'] ?? []),
            'telegramLinked' => filled($user->telegram_chat_id),
            'telegramConfigured' => $this->credentials->has(IntegrationCredentials::TELEGRAM_BOT_TOKEN),
        ];
    }
}
