<?php

namespace Alpha\Journal\Ai\Providers;

/**
 * One model vendor. Implemented per provider so AiRunner — which owns the kill switch, the audit
 * row and the AiContext mark — never has to know which vendor a feature is routed to.
 *
 * The contract is deliberately narrow: structured output only. Every AI feature in this app
 * extracts, classifies or ranks into a known shape (CLAUDE.md §6 — "it ranks, flags, drafts and
 * extracts"), and free-form prose responses are what make model output unsafe to act on. A
 * provider that cannot be made to answer through a schema does not belong behind this interface.
 */
interface AiProvider
{
    /** Stable key used in config, in ai_runs, and to look up the stored credential. */
    public function name(): string;

    /** True when this provider has a usable credential configured. */
    public function isConfigured(): bool;

    /**
     * Sends one structured request and returns the parsed payload plus token usage.
     *
     * @param  array<string, mixed>  $schema  JSON Schema the response must conform to
     * @return array{data: array<string, mixed>, input_tokens: int, output_tokens: int}
     *
     * @throws \RuntimeException on transport failure, refusal, or a response that does not conform
     */
    public function structured(string $model, string $system, string $userContent, string $toolName, array $schema): array;

    /**
     * A minimal round trip used by the admin "test connection" button.
     *
     * Separate from structured() because it must stay as cheap as possible — an admin checking a
     * pasted key should not be billed for a real extraction.
     */
    public function ping(): void;
}
