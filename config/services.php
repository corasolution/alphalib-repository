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

];
