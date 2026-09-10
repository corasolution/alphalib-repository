<?php

namespace Alpha\Journal\Notifications\Telegram;

use Alpha\Journal\Models\User;
use Alpha\Journal\Support\Secrets\IntegrationCredentials;
use Illuminate\Support\Str;

/**
 * Connects a user's Telegram account by handing them a one-time token inside a t.me deep link.
 *
 * A bot cannot message anyone who has not messaged it first, so linking has to start from the
 * user's side. The deep link carries the token as Telegram's `start` payload; pressing Start
 * sends it to our webhook along with the chat id, which is the only way we ever learn that id.
 *
 * Tokens are single-use and short-lived. A leaked one would let whoever holds it attach *their*
 * Telegram to someone else's account and receive that person's editorial notifications.
 */
class TelegramLinker
{
    private const TOKEN_TTL_MINUTES = 30;

    public function __construct(
        private readonly TelegramBotClient $client,
        private readonly IntegrationCredentials $credentials,
    ) {}

    /** Issues a fresh token, replacing any outstanding one for this user. */
    public function startLink(User $user): string
    {
        $token = Str::random(32);

        $user->update([
            'telegram_link_token' => $token,
            'telegram_link_expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
        ]);

        return $token;
    }

    /** The URL the "Connect Telegram" button opens, or null when no bot is configured. */
    public function deepLinkFor(User $user): ?string
    {
        $username = $this->credentials->for(IntegrationCredentials::TELEGRAM_BOT_USERNAME);

        if (blank($username) || ! $this->client->isConfigured()) {
            return null;
        }

        return 'https://t.me/'.ltrim($username, '@').'?start='.$this->startLink($user);
    }

    /**
     * Completes a link from a /start payload. Returns the user on success, null otherwise.
     *
     * The token is cleared whether or not it matched anything usable, so a token cannot be
     * replayed and an expired one cannot be resurrected by trying again.
     */
    public function completeLink(string $token, string $chatId): ?User
    {
        $user = User::where('telegram_link_token', $token)->first();

        if (! $user) {
            return null;
        }

        $expired = $user->telegram_link_expires_at === null || $user->telegram_link_expires_at->isPast();

        $user->update([
            'telegram_link_token' => null,
            'telegram_link_expires_at' => null,
        ] + ($expired ? [] : [
            'telegram_chat_id' => $chatId,
            'telegram_linked_at' => now(),
        ]));

        return $expired ? null : $user->fresh();
    }

    public function unlink(User $user): void
    {
        $user->update([
            'telegram_chat_id' => null,
            'telegram_linked_at' => null,
            'telegram_link_token' => null,
            'telegram_link_expires_at' => null,
        ]);
    }
}
