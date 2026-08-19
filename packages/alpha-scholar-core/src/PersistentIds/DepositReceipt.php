<?php

namespace Corasoft\AlphaScholar\PersistentIds;

final class DepositReceipt
{
    public function __construct(
        public readonly string $depositId,
        public readonly string $status,
        public readonly ?string $message = null,
    ) {}
}
