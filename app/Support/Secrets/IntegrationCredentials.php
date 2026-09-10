<?php

namespace Alpha\Journal\Support\Secrets;

/**
 * Credentials for outbound integrations — email transport and the Telegram bot — in
 * sites.integration_credentials.
 */
class IntegrationCredentials extends EncryptedSecrets
{
    public const BREVO_SMTP_KEY = 'brevo_smtp_key';

    public const BREVO_SMTP_USER = 'brevo_smtp_user';

    public const TELEGRAM_BOT_TOKEN = 'telegram_bot_token';

    public const TELEGRAM_BOT_USERNAME = 'telegram_bot_username';

    /**
     * Shared secret Telegram echoes back in the X-Telegram-Bot-Api-Secret-Token header.
     *
     * The webhook cannot use auth middleware — Telegram is not logged in — so this value is the
     * only thing standing between that route and the open internet.
     */
    public const TELEGRAM_WEBHOOK_SECRET = 'telegram_webhook_secret';

    protected function column(): string
    {
        return 'integration_credentials';
    }

    protected function configKeyFor(string $service): string
    {
        return "services.integrations.{$service}";
    }
}
