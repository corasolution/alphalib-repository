<?php

namespace Alpha\Journal\Ai\Providers;

use Alpha\Journal\Support\Secrets\EncryptedSecrets;

/**
 * API keys for the AI providers, in sites.ai_credentials.
 *
 * Kept separate from IntegrationCredentials rather than sharing one column: these are the keys
 * that spend money per request, and holding them apart means a screen or a log that touches
 * integration settings never has the billable ones in scope to begin with.
 */
class AiCredentials extends EncryptedSecrets
{
    protected function column(): string
    {
        return 'ai_credentials';
    }

    protected function configKeyFor(string $service): string
    {
        return "services.{$service}.key";
    }
}
