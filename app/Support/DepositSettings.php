<?php

namespace Alpha\Journal\Support;

use Alpha\Journal\Models\Site;
use Alpha\Journal\Support\Secrets\IntegrationCredentials;

/**
 * Where a deposit actually goes, and who it goes as.
 *
 * The endpoint is the dangerous part. Crossref and DataCite both run a sandbox that accepts a
 * deposit, answers "submitted", and registers nothing — so a misconfigured install looks exactly
 * like a working one right up until somebody clicks a DOI and it does not resolve. Because of
 * that the endpoint is not a free-text field: it is a live/test switch, and everything that
 * reports on indexing says which side of it the journal is on.
 *
 * Credentials follow the same two-source contract as every other integration (see
 * EncryptedSecrets): what a site admin saved wins, .env is the fallback.
 */
class DepositSettings
{
    public const CROSSREF_LIVE_URL = 'https://doi.crossref.org/servlet/deposit';

    public const CROSSREF_TEST_URL = 'https://test.crossref.org/servlet/deposit';

    public const DATACITE_LIVE_URL = 'https://api.datacite.org';

    public const DATACITE_TEST_URL = 'https://api.test.datacite.org';

    public function __construct(private readonly IntegrationCredentials $credentials) {}

    public function crossrefUsername(): ?string
    {
        return $this->credentials->for(IntegrationCredentials::CROSSREF_USERNAME)
            ?? config('services.crossref.username');
    }

    public function crossrefPassword(): ?string
    {
        return $this->credentials->for(IntegrationCredentials::CROSSREF_PASSWORD)
            ?? config('services.crossref.password');
    }

    public function crossrefDepositorName(): string
    {
        return $this->credentials->for(IntegrationCredentials::CROSSREF_DEPOSITOR_NAME)
            ?? config('services.crossref.depositor_name')
            ?? Site::query()->value('title')
            ?? 'Alpha Journal';
    }

    public function crossrefDepositorEmail(): ?string
    {
        return $this->credentials->for(IntegrationCredentials::CROSSREF_DEPOSITOR_EMAIL)
            ?? config('services.crossref.depositor_email');
    }

    public function dataciteUsername(): ?string
    {
        return $this->credentials->for(IntegrationCredentials::DATACITE_USERNAME)
            ?? config('services.datacite.username');
    }

    public function datacitePassword(): ?string
    {
        return $this->credentials->for(IntegrationCredentials::DATACITE_PASSWORD)
            ?? config('services.datacite.password');
    }

    /**
     * An explicit .env deposit URL still wins, so an installation pointed at a local mock or a
     * Crossref staging host keeps working; otherwise the admin switch decides.
     */
    public function crossrefDepositUrl(): string
    {
        return config('services.crossref.deposit_url_override')
            ?? ($this->crossrefIsLive() ? self::CROSSREF_LIVE_URL : self::CROSSREF_TEST_URL);
    }

    public function dataciteBaseUrl(): string
    {
        return config('services.datacite.base_url_override')
            ?? ($this->dataciteIsLive() ? self::DATACITE_LIVE_URL : self::DATACITE_TEST_URL);
    }

    public function crossrefIsLive(): bool
    {
        return (bool) ($this->settings()['crossref_live'] ?? false);
    }

    public function dataciteIsLive(): bool
    {
        return (bool) ($this->settings()['datacite_live'] ?? false);
    }

    /** Both halves of a login are needed; one on its own deposits nothing. */
    public function crossrefIsConfigured(): bool
    {
        return filled($this->crossrefUsername()) && filled($this->crossrefPassword());
    }

    public function dataciteIsConfigured(): bool
    {
        return filled($this->dataciteUsername()) && filled($this->datacitePassword());
    }

    public function setLive(string $service, bool $live): void
    {
        $site = Site::firstOrFail();
        $site->settings = array_merge($site->settings ?? [], ["{$service}_live" => $live]);
        $site->save();
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return Site::query()->value('settings') ?? [];
    }
}
