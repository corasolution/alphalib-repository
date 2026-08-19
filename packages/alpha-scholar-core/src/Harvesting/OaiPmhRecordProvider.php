<?php

namespace Corasoft\AlphaScholar\Harvesting;

/**
 * Feeds the OAI-PMH 2.0 provider endpoint /oai (CLAUDE.md §5.1). Implemented in P2.
 */
interface OaiPmhRecordProvider
{
    public function listSets(): array;

    public function listIdentifiers(string $metadataFormat, ?string $set, ?string $resumptionToken): array;

    public function getRecord(string $identifier, string $metadataFormat): ?array;
}
