<?php

namespace Alpha\Journal\Notifications\Telegram;

use Alpha\Journal\Support\Secrets\IntegrationCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over the Telegram Bot API.
 *
 * Messages are sent as plain text with no parse_mode. Templates are admin-editable prose
 * (CLAUDE.md §11.1), and Telegram's Markdown mode rejects an unescaped underscore or asterisk
 * with a 400 — so a journal manager writing "peer_review" into a template would silently stop
 * their own notifications going out. Plain text cannot fail that way.
 */
class TelegramBotClient
{
    public function __construct(private readonly IntegrationCredentials $credentials) {}

    public function isConfigured(): bool
    {
        return $this->credentials->has(IntegrationCredentials::TELEGRAM_BOT_TOKEN);
    }

    public function botUsername(): ?string
    {
        return $this->credentials->for(IntegrationCredentials::TELEGRAM_BOT_USERNAME);
    }

    public function sendMessage(string $chatId, string $text): void
    {
        $response = Http::timeout(15)
            ->retry(2, 500, fn ($e) => true, throw: false)
            ->post($this->endpoint('sendMessage'), [
                'chat_id' => $chatId,
                'text' => $text,
                'disable_web_page_preview' => true,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Telegram sendMessage failed ({$response->status()}): ".mb_substr($response->body(), 0, 300)
            );
        }
    }

    /** Confirms the token is valid and returns the bot's own username. */
    public function getMe(): array
    {
        $response = Http::timeout(15)->get($this->endpoint('getMe'));

        if ($response->failed()) {
            throw new RuntimeException(
                "Telegram getMe failed ({$response->status()}): ".mb_substr($response->body(), 0, 300)
            );
        }

        return (array) $response->json('result', []);
    }

    /**
     * Points Telegram at our webhook and sets the shared secret it must echo back.
     *
     * Telegram will only deliver to HTTPS, so this cannot succeed against a local dev server —
     * the admin screen surfaces the API's own error rather than pretending otherwise.
     */
    public function setWebhook(string $url, string $secret): void
    {
        $response = Http::timeout(15)->post($this->endpoint('setWebhook'), [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
        ]);

        if ($response->failed() || $response->json('ok') !== true) {
            throw new RuntimeException(
                'Telegram setWebhook failed: '.mb_substr($response->body(), 0, 300)
            );
        }
    }

    private function endpoint(string $method): string
    {
        $token = $this->credentials->for(IntegrationCredentials::TELEGRAM_BOT_TOKEN);

        if ($token === null) {
            throw new RuntimeException('No Telegram bot token is configured.');
        }

        return "https://api.telegram.org/bot{$token}/{$method}";
    }
}
