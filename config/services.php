<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Crossref deposit (CLAUDE.md §5.2). deposit_url defaults to the *test* endpoint on purpose —
    // switch to https://doi.crossref.org/servlet/deposit only for real production deposits.
    'crossref' => [
        'username' => env('CROSSREF_USERNAME'),
        'password' => env('CROSSREF_PASSWORD'),
        'depositor_name' => env('CROSSREF_DEPOSITOR_NAME', 'Alpha Journal'),
        'depositor_email' => env('CROSSREF_DEPOSITOR_EMAIL'),
        'deposit_url' => env('CROSSREF_DEPOSIT_URL', 'https://test.crossref.org/servlet/deposit'),
    ],

    // ORCID OAuth "connect your account" flow + work-push API (CLAUDE.md §5.5). Defaults point at
    // the ORCID sandbox — switch to orcid.org / api.orcid.org only for real production use.
    'orcid' => [
        'client_id' => env('ORCID_CLIENT_ID'),
        'client_secret' => env('ORCID_CLIENT_SECRET'),
        'authorize_url' => env('ORCID_AUTHORIZE_URL', 'https://sandbox.orcid.org/oauth/authorize'),
        'token_url' => env('ORCID_TOKEN_URL', 'https://sandbox.orcid.org/oauth/token'),
        'api_url' => env('ORCID_API_URL', 'https://api.sandbox.orcid.org/v3.0'),
        'scope' => '/read-limited /activities/update',
    ],

    // ROR affiliation autocomplete (CLAUDE.md §5.5) — public API, no credentials required.
    'ror' => [
        'base_url' => env('ROR_BASE_URL', 'https://api.ror.org'),
    ],

    // DOAJ article upload (CLAUDE.md §5.3). The API key itself is per-journal, not global — it
    // lives in journal_settings.doaj_api_key, since each journal has its own DOAJ account.
    'doaj' => [
        'base_url' => env('DOAJ_BASE_URL', 'https://doaj.org'),
    ],

    // DataCite DOI deposit (CLAUDE.md §5.5) — installation-wide credentials, like Crossref, not
    // per-journal like DOAJ. base_url defaults to the DataCite test/sandbox API.
    'datacite' => [
        'username' => env('DATACITE_USERNAME'),
        'password' => env('DATACITE_PASSWORD'),
        'base_url' => env('DATACITE_BASE_URL', 'https://api.test.datacite.org'),
    ],

    // Outbound integrations. Fallbacks only — the authoritative values are the encrypted ones
    // a site admin sets in Admin → Notifications (see IntegrationCredentials).
    'integrations' => [
        'brevo_smtp_user' => env('BREVO_SMTP_USER'),
        'brevo_smtp_key' => env('BREVO_SMTP_KEY'),
        'brevo_host' => env('BREVO_HOST', 'smtp-relay.brevo.com'),
        'brevo_port' => env('BREVO_PORT', 587),
        'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'telegram_bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'telegram_webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    // AI provider credentials (CLAUDE.md §6). These are FALLBACKS: the authoritative key is the
    // encrypted one a site admin sets in Admin → AI, and AiCredentials prefers that. Either way a
    // key being present must never by itself mean a journal is processed — per-journal consent
    // (journal_settings.ai_enabled) is a separate switch.
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
    ],

];
