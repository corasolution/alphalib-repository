<?php

namespace Alpha\Journal\Orcid;

final class OrcidTokenResult
{
    public function __construct(
        public readonly string $orcid,
        public readonly string $accessToken,
        public readonly int $expiresIn,
    ) {}
}
