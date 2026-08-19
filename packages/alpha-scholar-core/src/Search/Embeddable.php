<?php

namespace Corasoft\AlphaScholar\Search;

/**
 * Implemented by anything embedded into pgvector for reviewer matching (A3) or reader search (A8). P5.
 */
interface Embeddable
{
    public function embeddingSourceText(): string;

    public function embeddingKey(): string;
}
