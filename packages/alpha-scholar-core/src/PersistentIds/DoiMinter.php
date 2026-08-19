<?php

namespace Corasoft\AlphaScholar\PersistentIds;

/**
 * Crossref/DataCite DOI deposit contract. First implementation lands in P2 (CLAUDE.md §5.2) as
 * Alpha\Journal\Crossref\CrossrefDoiMinter — signature corrected from the P0 sketch once the real
 * shape was known: metadata is a serialized XML document (a string), not an array.
 */
interface DoiMinter
{
    public function deposit(string $doi, string $metadataXml): DepositReceipt;

    public function status(string $depositId): DepositReceipt;
}
