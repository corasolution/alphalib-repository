<?php

namespace Alpha\Journal\Http\Controllers\Admin;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Notifications\Telegram\TelegramBotClient;
use Alpha\Journal\Support\Secrets\IntegrationCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Installation-wide delivery settings: the Brevo SMTP credential and the Telegram bot.
 *
 * Stored secrets are never returned to the browser — only whether one is set, where it came from,
 * and its last four characters. Same posture as Admin → AI.
 */
class NotificationSettingsController extends Controller
{
    private const SECRETS = [
        IntegrationCredentials::BREVO_SMTP_USER,
        IntegrationCredentials::BREVO_SMTP_KEY,
        IntegrationCredentials::TELEGRAM_BOT_TOKEN,
        IntegrationCredentials::TELEGRAM_BOT_USERNAME,
    ];

    public function index(IntegrationCredentials $credentials, TelegramBotClient $telegram): Response
    {
        Gate::authorize('manage', Site::class);

        return Inertia::render('admin/notifications', [
            'brevo' => [
                'user' => $credentials->for(IntegrationCredentials::BREVO_SMTP_USER),
                'configured' => $credentials->has(IntegrationCredentials::BREVO_SMTP_KEY),
                'source' => $credentials->source(IntegrationCredentials::BREVO_SMTP_KEY),
                'hint' => $credentials->hint(IntegrationCredentials::BREVO_SMTP_KEY),
            ],
            'telegram' => [
                'configured' => $telegram->isConfigured(),
                'source' => $credentials->source(IntegrationCredentials::TELEGRAM_BOT_TOKEN),
                'hint' => $credentials->hint(IntegrationCredentials::TELEGRAM_BOT_TOKEN),
                'username' => $credentials->for(IntegrationCredentials::TELEGRAM_BOT_USERNAME),
                'webhookConfigured' => $credentials->has(IntegrationCredentials::TELEGRAM_WEBHOOK_SECRET),
                'webhookUrl' => route('telegram.webhook'),
            ],
            'mailFrom' => config('mail.from.address'),
        ]);
    }

    public function updateSecret(Request $request, IntegrationCredentials $credentials): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $data = $request->validate([
            'name' => ['required', Rule::in(self::SECRETS)],
            'value' => ['nullable', 'string', 'max:500'],
        ]);

        $credentials->put($data['name'], $data['value'] ?? null);

        return back()->with('success', filled($data['value'] ?? null) ? 'Saved.' : 'Removed.');
    }

    /** Sends a real email to the acting admin, so "is Brevo working" has a definite answer. */
    public function testEmail(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        try {
            Mail::raw(
                "This is a test message from Alpha Journal.\n\nIf you are reading it, outbound email is working.",
                fn ($message) => $message->to($request->user()->email)->subject('Alpha Journal test email'),
            );
        } catch (Throwable $e) {
            return back()->withErrors(['value' => 'Send failed — '.mb_substr($e->getMessage(), 0, 300)]);
        }

        return back()->with('success', "Test email sent to {$request->user()->email}.");
    }

    /**
     * Registers our webhook with Telegram, generating the shared secret it must echo back.
     *
     * Telegram only delivers to a public HTTPS URL, so this necessarily fails against a local dev
     * server. The API's own error is surfaced verbatim rather than dressed up — a developer
     * seeing "bad webhook: HTTPS url must be provided" learns more than a generic failure would
     * tell them.
     */
    public function registerWebhook(TelegramBotClient $telegram, IntegrationCredentials $credentials): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $secret = Str::random(48);

        try {
            $telegram->setWebhook(route('telegram.webhook'), $secret);
        } catch (Throwable $e) {
            return back()->withErrors(['value' => mb_substr($e->getMessage(), 0, 400)]);
        }

        // Stored only after Telegram accepted it, or a failed call would leave us expecting a
        // secret Telegram is not sending, silently rejecting every real update.
        $credentials->put(IntegrationCredentials::TELEGRAM_WEBHOOK_SECRET, $secret);

        return back()->with('success', 'Telegram webhook registered.');
    }

    public function testTelegram(Request $request, TelegramBotClient $telegram): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $chatId = $request->user()->telegram_chat_id;

        if (blank($chatId)) {
            return back()->withErrors(['value' => 'Connect your own Telegram in Settings → Notifications first, so there is somewhere to send the test.']);
        }

        try {
            $telegram->sendMessage((string) $chatId, 'Alpha Journal test message. Telegram notifications are working.');
        } catch (Throwable $e) {
            return back()->withErrors(['value' => 'Send failed — '.mb_substr($e->getMessage(), 0, 300)]);
        }

        return back()->with('success', 'Test message sent to your Telegram.');
    }
}
