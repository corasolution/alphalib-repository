<?php

namespace Alpha\Journal\Mail;

final class RenderedEmailTemplate
{
    public function __construct(
        public readonly string $subject,
        public readonly string $body,
    ) {}
}
