<?php

namespace Alpha\Journal\Http\Controllers;

use Alpha\Journal\Notifications\Telegram\TelegramBotClient;
use Alpha\Journal\Notifications\Telegram\TelegramLinker;
use Alpha\Journal\Support\Secrets\IntegrationCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Receives updates from the Telegram Bot API.
 *
 * This route cannot sit behind `auth` — Telegram is not a logged-in user — so the shared secret
 * it echoes back in X-Telegram-Bot-Api-Secret-Token is the ONLY thing separating this endpoint
 * from the open internet. Anyone who could post here unchecked could attach their own Telegram
 * to another person's account and start receiving that person's editorial notifications.
 *
 * Everything else is answered 200 regardless of outcome: Telegram retries non-2xx responses, and
 * retrying an update we have already decided we cannot use just repeats the same work forever.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        IntegrationCredentials $credentials,
        TelegramLinker $linker,
        TelegramBotClient $client,
    ): JsonResponse {
        $this->assertFromTelegram($request, $credentials);

        $message = (array) $request->input('message', []);
        $text = trim((string) ($message['text'] ?? ''));
        $chatId = $message['chat']['id'] ?? null;

        if (blank($chatId) || ! str_starts_with($text, '/start')) {
            return response()->json(['ok' => true]);
        }

        $token = trim(mb_substr($text, mb_strlen('/start')));

        if ($token === '') {
            $this->reply($client, (string) $chatId, 'Open the Connect Telegram link from your notification settings to finish linking this chat.');

            return response()->json(['ok' => true]);
        }

        $user = $linker->completeLink($token, (string) $chatId);

        $this->reply($client, (string) $chatId, $user
            ? "Linked. You'll get journal notifications here, {$user->name}."
            : 'That link has expired or has already been used. Please start again from your notification settings.');

        return response()->json(['ok' => true]);
    }

    private function assertFromTelegram(Request $request, IntegrationCredentials $credentials): void
    {
        $expected = $credentials->for(IntegrationCredentials::TELEGRAM_WEBHOOK_SECRET);
        $given = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        // No configured secret means the webhook was never set up — refuse rather than accept
        // everything, which is what an empty-equals-empty comparison would have done.
        if (blank($expected) || ! hash_equals($expected, $given)) {
            throw new AccessDeniedHttpException('Invalid Telegram webhook secret.');
        }
    }

    /** A failed courtesy reply must not fail the webhook — the link itself already succeeded. */
    private function reply(TelegramBotClient $client, string $chatId, string $text): void
    {
        try {
            $client->sendMessage($chatId, $text);
        } catch (\Throwable) {
            // Ignored on purpose.
        }
    }
}
