<?php

namespace Alpha\Journal\Ror;

final class RorOrganization
{
    public function __construct(
        public readonly string $rorId,
        public readonly string $name,
        public readonly ?string $country = null,
    ) {}
}
