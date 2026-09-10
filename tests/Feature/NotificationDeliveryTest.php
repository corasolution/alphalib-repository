<?php

namespace Tests\Feature;

use Alpha\Journal\Mail\RenderedEmailTemplate;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\User;
use Alpha\Journal\Notifications\NotificationPreferences;
use Alpha\Journal\Notifications\Telegram\TelegramChannel;
use Alpha\Journal\Notifications\Telegram\TelegramLinker;
use Alpha\Journal\Notifications\TemplatedNotification;
use Alpha\Journal\Support\Secrets\IntegrationCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Delivery of the review and editorial notifications: which channels a message goes out on, and
 * the Telegram link flow that makes the second channel possible at all.
 *
 * The template *content* is covered elsewhere — this file is about routing and transport.
 */
class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create(['title' => 'Test Site']);

        config()->set('services.integrations.telegram_bot_token', null);
        config()->set('services.integrations.telegram_bot_username', null);
        config()->set('services.integrations.telegram_webhook_secret', null);
        config()->set('services.integrations.brevo_smtp_key', null);
    }

    private function template(string $subject = 'Please review a submission'): RenderedEmailTemplate
    {
        return new RenderedEmailTemplate($subject, "Dear reviewer,\n\nWe would value your assessment.");
    }

    private function configureTelegram(): void
    {
        $credentials = app(IntegrationCredentials::class);
        $credentials->put(IntegrationCredentials::TELEGRAM_BOT_TOKEN, '123456:test-token');
        $credentials->put(IntegrationCredentials::TELEGRAM_BOT_USERNAME, 'AlphaJournalBot');
    }

    private function linkedUser(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'telegram_chat_id' => '999888',
            'telegram_linked_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------ channel routing

    public function test_email_is_the_default_for_a_user_who_has_never_touched_preferences(): void
    {
        $user = User::factory()->create();

        // Email defaults ON deliberately: a journal that silently stops emailing its reviewers
        // stops working, so silence must be something a user chose, never something inherited.
        $this->assertSame(['mail'], app(NotificationPreferences::class)->channelsFor($user));
    }

    public function test_telegram_is_not_used_until_the_user_has_linked_a_chat(): void
    {
        $this->configureTelegram();
        $unlinked = User::factory()->create(['notification_preferences' => ['mail' => true, 'telegram' => true]]);

        // The preference defaults to on, which is only safe because this gate exists.
        $this->assertSame(['mail'], app(NotificationPreferences::class)->channelsFor($unlinked));
    }

    public function test_telegram_is_not_used_when_the_installation_has_no_bot(): void
    {
        $linked = $this->linkedUser(['notification_preferences' => ['mail' => true, 'telegram' => true]]);

        // A stored chat id is worthless without a bot to send from — asking anyway would be a
        // guaranteed failed send on every notification.
        $this->assertSame(['mail'], app(NotificationPreferences::class)->channelsFor($linked));
    }

    public function test_a_linked_user_with_a_configured_bot_gets_both(): void
    {
        $this->configureTelegram();
        $user = $this->linkedUser();

        $this->assertSame(['mail', 'telegram'], app(NotificationPreferences::class)->channelsFor($user));
    }

    public function test_switching_a_channel_off_removes_only_that_channel(): void
    {
        $this->configureTelegram();
        $user = $this->linkedUser(['notification_preferences' => ['mail' => false, 'telegram' => true]]);

        $this->assertSame(['telegram'], app(NotificationPreferences::class)->channelsFor($user));
    }

    public function test_a_muted_template_is_silenced_on_every_channel(): void
    {
        $this->configureTelegram();
        $user = $this->linkedUser(['notification_preferences' => [
            'mail' => true, 'telegram' => true, 'muted' => ['review.reminder_upcoming'],
        ]]);

        $preferences = app(NotificationPreferences::class);

        $this->assertSame([], $preferences->channelsFor($user, 'review.reminder_upcoming'));
        // Muting one kind of message must not mute the rest.
        $this->assertSame(['mail', 'telegram'], $preferences->channelsFor($user, 'review.invitation'));
    }

    public function test_the_notification_routes_to_the_channels_preferences_chose(): void
    {
        $this->configureTelegram();
        $user = $this->linkedUser();

        $channels = (new TemplatedNotification($this->template(), 'review.invitation'))->via($user);

        $this->assertContains('mail', $channels);
        $this->assertContains(TelegramChannel::class, $channels);
    }

    public function test_an_on_demand_recipient_still_gets_email(): void
    {
        // Invitations go to people with no account, so there are no preferences to consult. That
        // path must not silently send nothing.
        $notifiable = Notification::route('mail', 'stranger@example.com');

        $this->assertSame(['mail'], (new TemplatedNotification($this->template(), 'journal.invitation'))->via($notifiable));
    }

    public function test_a_muted_notification_sends_nothing_at_all(): void
    {
        Notification::fake();
        $user = User::factory()->create(['notification_preferences' => ['mail' => true, 'muted' => ['review.invitation']]]);

        $user->notify(new TemplatedNotification($this->template(), 'review.invitation'));

        Notification::assertNothingSentTo($user);
    }

    // ------------------------------------------------------------------ telegram transport

    public function test_the_telegram_message_carries_the_subject_and_body(): void
    {
        $this->configureTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $user = $this->linkedUser();
        app(TelegramChannel::class)->send($user, new TemplatedNotification($this->template('Review request'), 'review.invitation'));

        Http::assertSent(function ($request) {
            // Telegram has no subject line, so the subject is repeated as a first line — without
            // it the message opens mid-sentence on a phone.
            return str_contains($request->url(), '/sendMessage')
                && $request['chat_id'] === '999888'
                && str_starts_with($request['text'], 'Review request')
                && str_contains($request['text'], 'We would value your assessment.');
        });
    }

    public function test_an_unlinked_user_is_a_no_op_rather_than_a_failed_send(): void
    {
        $this->configureTelegram();
        Http::fake();

        app(TelegramChannel::class)->send(User::factory()->create(), new TemplatedNotification($this->template()));

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------ link flow

    public function test_the_deep_link_carries_a_single_use_token(): void
    {
        $this->configureTelegram();
        $user = User::factory()->create();

        $link = app(TelegramLinker::class)->deepLinkFor($user);

        $this->assertStringStartsWith('https://t.me/AlphaJournalBot?start=', $link);
        $this->assertNotNull($user->fresh()->telegram_link_token);
        $this->assertStringEndsWith($user->fresh()->telegram_link_token, $link);
    }

    public function test_there_is_no_deep_link_without_a_configured_bot(): void
    {
        $this->assertNull(app(TelegramLinker::class)->deepLinkFor(User::factory()->create()));
    }

    public function test_a_valid_token_links_the_chat_and_cannot_be_replayed(): void
    {
        $this->configureTelegram();
        $user = User::factory()->create();
        $token = app(TelegramLinker::class)->startLink($user);

        $linked = app(TelegramLinker::class)->completeLink($token, '555111');

        $this->assertNotNull($linked);
        $this->assertSame('555111', $user->fresh()->telegram_chat_id);

        // Replaying the same token must not attach a second, different chat to this account.
        $this->assertNull(app(TelegramLinker::class)->completeLink($token, '777222'));
        $this->assertSame('555111', $user->fresh()->telegram_chat_id);
    }

    public function test_an_expired_token_links_nothing_and_is_burned(): void
    {
        $this->configureTelegram();
        $user = User::factory()->create();
        $token = app(TelegramLinker::class)->startLink($user);
        $user->update(['telegram_link_expires_at' => now()->subMinute()]);

        $this->assertNull(app(TelegramLinker::class)->completeLink($token, '555111'));
        $this->assertNull($user->fresh()->telegram_chat_id);
        // Burned regardless, so an expired token cannot be resurrected by trying again.
        $this->assertNull($user->fresh()->telegram_link_token);
    }

    // ------------------------------------------------------------------ webhook security

    public function test_the_webhook_rejects_a_request_with_no_secret(): void
    {
        $this->configureTelegram();
        app(IntegrationCredentials::class)->put(IntegrationCredentials::TELEGRAM_WEBHOOK_SECRET, 'the-real-secret');
        $user = User::factory()->create();
        $token = app(TelegramLinker::class)->startLink($user);

        $this->postJson(route('telegram.webhook'), [
            'message' => ['chat' => ['id' => 42], 'text' => "/start {$token}"],
        ])->assertForbidden();

        // The whole point: an unauthenticated caller must not be able to attach their own chat
        // to somebody else's account and start receiving that person's editorial notifications.
        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_the_webhook_rejects_a_wrong_secret(): void
    {
        $this->configureTelegram();
        app(IntegrationCredentials::class)->put(IntegrationCredentials::TELEGRAM_WEBHOOK_SECRET, 'the-real-secret');
        $user = User::factory()->create();
        $token = app(TelegramLinker::class)->startLink($user);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'not-the-secret')
            ->postJson(route('telegram.webhook'), [
                'message' => ['chat' => ['id' => 42], 'text' => "/start {$token}"],
            ])->assertForbidden();

        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_the_webhook_rejects_everything_when_no_secret_is_configured(): void
    {
        $this->configureTelegram();

        // An empty-equals-empty comparison would have accepted every request on an installation
        // that never registered its webhook.
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', '')
            ->postJson(route('telegram.webhook'), ['message' => ['chat' => ['id' => 42], 'text' => '/start abc']])
            ->assertForbidden();
    }

    public function test_a_correctly_signed_start_links_the_chat(): void
    {
        $this->configureTelegram();
        app(IntegrationCredentials::class)->put(IntegrationCredentials::TELEGRAM_WEBHOOK_SECRET, 'the-real-secret');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $user = User::factory()->create();
        $token = app(TelegramLinker::class)->startLink($user);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'the-real-secret')
            ->postJson(route('telegram.webhook'), [
                'message' => ['chat' => ['id' => 424242], 'text' => "/start {$token}"],
            ])->assertOk();

        $this->assertSame('424242', $user->fresh()->telegram_chat_id);
    }

    public function test_an_unrecognised_update_is_acknowledged_rather_than_retried(): void
    {
        $this->configureTelegram();
        app(IntegrationCredentials::class)->put(IntegrationCredentials::TELEGRAM_WEBHOOK_SECRET, 'the-real-secret');

        // Telegram retries any non-2xx, and retrying an update we have already decided we cannot
        // use just repeats the same work forever.
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'the-real-secret')
            ->postJson(route('telegram.webhook'), ['message' => ['chat' => ['id' => 1], 'text' => 'hello there']])
            ->assertOk();
    }

    // ------------------------------------------------------------------ user settings

    public function test_a_user_can_change_their_own_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('notifications.update'), [
            'mail' => true,
            'telegram' => false,
            'muted' => ['review.reminder_upcoming'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['review.reminder_upcoming'], $user->fresh()->notification_preferences['muted']);
    }

    public function test_an_unknown_mute_key_is_rejected(): void
    {
        $user = User::factory()->create();

        // Otherwise a user could store unbounded junk in a column read on every send.
        $this->actingAs($user)->put(route('notifications.update'), [
            'mail' => true, 'telegram' => false, 'muted' => ['not.a.real.template'],
        ])->assertSessionHasErrors('muted.0');
    }

    public function test_disconnecting_telegram_clears_the_chat(): void
    {
        $user = $this->linkedUser();

        $this->actingAs($user)->delete(route('notifications.telegram.unlink'))->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    // ------------------------------------------------------------------ admin screen

    public function test_an_admin_can_store_delivery_secrets_without_them_being_readable_back(): void
    {
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->put(route('admin.notifications.secret'), [
            'name' => IntegrationCredentials::BREVO_SMTP_KEY,
            'value' => 'xsmtpsib-secret-value',
        ])->assertSessionHasNoErrors();

        $this->assertSame('xsmtpsib-secret-value', app(IntegrationCredentials::class)->for(IntegrationCredentials::BREVO_SMTP_KEY));

        $response = $this->actingAs($admin)->get(route('admin.notifications.index'));
        $response->assertOk();
        $response->assertDontSee('xsmtpsib-secret-value');
    }

    public function test_an_arbitrary_secret_name_is_rejected(): void
    {
        $admin = User::factory()->create(['is_site_admin' => true]);

        // The name indexes into an encrypted map; an unbounded one would let an admin write keys
        // nothing reads, or shadow one that something does.
        $this->actingAs($admin)->put(route('admin.notifications.secret'), [
            'name' => 'something_else', 'value' => 'x',
        ])->assertSessionHasErrors('name');
    }

    public function test_a_regular_user_cannot_reach_the_delivery_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.notifications.index'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.notifications.secret'), [
            'name' => IntegrationCredentials::TELEGRAM_BOT_TOKEN, 'value' => 'stolen',
        ])->assertForbidden();
        $this->actingAs($user)->post(route('admin.notifications.test-email'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.notifications.telegram.register'))->assertForbidden();

        $this->assertNull(app(IntegrationCredentials::class)->for(IntegrationCredentials::TELEGRAM_BOT_TOKEN));
    }

    // ------------------------------------------------------------------ brevo config

    public function test_a_stored_brevo_key_takes_over_the_default_mailer(): void
    {
        app(IntegrationCredentials::class)->put(IntegrationCredentials::BREVO_SMTP_USER, 'smtp-login@example.com');
        app(IntegrationCredentials::class)->put(IntegrationCredentials::BREVO_SMTP_KEY, 'xsmtpsib-key');

        // Resolving the mail manager is what triggers configuration — it is deferred so an
        // ordinary web request does not pay a database read for a mailer it never uses.
        app(\Illuminate\Mail\MailManager::class);

        $this->assertSame('brevo', config('mail.default'));
        $this->assertSame('smtp-relay.brevo.com', config('mail.mailers.brevo.host'));
        $this->assertSame('xsmtpsib-key', config('mail.mailers.brevo.password'));
        $this->assertSame('smtp-login@example.com', config('mail.mailers.brevo.username'));
    }

    public function test_no_stored_key_leaves_the_configured_mailer_alone(): void
    {
        config()->set('mail.default', 'log');

        app(\Illuminate\Mail\MailManager::class);

        // An install that has always used its own SMTP server must not be switched to Brevo
        // behind its back.
        $this->assertSame('log', config('mail.default'));
    }
}
