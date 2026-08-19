<?php

namespace Alpha\Journal\Oai;

use InvalidArgumentException;

/**
 * oai:{host}:{journalPath}/{publicationId} (OAI-PMH 2.0 §2.4 identifier syntax).
 */
final class OaiIdentifier
{
    private function __construct(
        public readonly string $journalPath,
        public readonly int $publicationId,
    ) {}

    public static function for(string $journalPath, int $publicationId): self
    {
        return new self($journalPath, $publicationId);
    }

    public static function parse(string $identifier): self
    {
        if (! preg_match('#^oai:[^:]+:([^/]+)/(\d+)$#', $identifier, $matches)) {
            throw new InvalidArgumentException("Malformed OAI identifier: {$identifier}");
        }

        return new self($matches[1], (int) $matches[2]);
    }

    public function toString(): string
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return "oai:{$host}:{$this->journalPath}/{$this->publicationId}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
