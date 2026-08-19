<?php

namespace Alpha\Journal\Oai;

final class OaiRecord
{
    /** @param string[] $setSpecs */
    public function __construct(
        public readonly OaiIdentifier $identifier,
        public readonly string $datestamp,
        public readonly array $setSpecs,
        public readonly int $publicationId,
    ) {}
}
