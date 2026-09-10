<?php

namespace Alpha\Journal\Support\Secrets;

use Alpha\Journal\Models\Site;

/**
 * A map of service => secret held in one encrypted column on the sites row.
 *
 * Two sources, in order: what a site admin saved, then the .env value. The .env fallback is what
 * keeps an existing self-hosted install working after a credential moves into the database —
 * nobody has to visit a screen before their integrations keep functioning — while a hosted tier
 * gets self-service rotation without a server login.
 *
 * Reads are not cached. A rotated key must take effect on the next request, not after a deploy;
 * a stale credential is a far worse failure than one extra query.
 *
 * Subclassed rather than configured per call so each caller names its own column and config
 * namespace once. AiCredentials was the first of these; Brevo and Telegram are the second, which
 * is the point at which copying it a third time stops being acceptable.
 */
abstract class EncryptedSecrets
{
    /** The encrypted:array column on `sites` holding this map. */
    abstract protected function column(): string;

    /** Where the .env fallback for a given service lives in config. */
    abstract protected function configKeyFor(string $service): string;

    public function for(string $service): ?string
    {
        $stored = $this->stored();

        if (filled($stored[$service] ?? null)) {
            return $stored[$service];
        }

        $fallback = config($this->configKeyFor($service));

        return filled($fallback) ? $fallback : null;
    }

    public function has(string $service): bool
    {
        return $this->for($service) !== null;
    }

    /** Where a configured secret came from, for an admin screen to show. */
    public function source(string $service): string
    {
        if (filled($this->stored()[$service] ?? null)) {
            return 'database';
        }

        return filled(config($this->configKeyFor($service))) ? 'env' : 'none';
    }

    /**
     * Last four characters only, so a screen can show which secret is loaded without ever
     * transmitting a usable credential back to a browser.
     */
    public function hint(string $service): ?string
    {
        $secret = $this->for($service);

        return $secret === null ? null : '…'.mb_substr($secret, -4);
    }

    /** Saves or clears one service's secret, leaving the others untouched. */
    public function put(string $service, ?string $secret): void
    {
        $site = Site::firstOrFail();
        $secrets = $this->stored();

        if (filled($secret)) {
            $secrets[$service] = trim($secret);
        } else {
            unset($secrets[$service]);
        }

        $site->update([$this->column() => $secrets ?: null]);
    }

    /** @return array<string, string> */
    protected function stored(): array
    {
        $secrets = Site::first()?->{$this->column()};

        return is_array($secrets) ? $secrets : [];
    }
}
