<?php

namespace Alpha\Journal\Ai\Providers;

use RuntimeException;

/**
 * Which vendor serves a given model.
 *
 * Routing is by model-id prefix rather than a second config key naming the provider. A feature's
 * config then holds one value instead of two that can disagree — and "claude-haiku-4-5 is served
 * by anthropic" is not a fact worth letting an operator get wrong in a config file.
 */
class ProviderRegistry
{
    /** @var array<string, AiProvider> */
    private array $providers;

    public function __construct(AnthropicProvider $anthropic, GeminiProvider $gemini)
    {
        $this->providers = [
            $anthropic->name() => $anthropic,
            $gemini->name() => $gemini,
        ];
    }

    /** @return array<string, AiProvider> */
    public function all(): array
    {
        return $this->providers;
    }

    public function get(string $name): AiProvider
    {
        return $this->providers[$name] ?? throw new RuntimeException("Unknown AI provider [{$name}].");
    }

    /** The provider name a model id belongs to. */
    public function providerNameFor(string $model): string
    {
        return match (true) {
            str_starts_with($model, 'claude-') => 'anthropic',
            str_starts_with($model, 'gemini-') => 'gemini',
            default => throw new RuntimeException(
                "Cannot tell which provider serves model [{$model}] — expected a claude-* or gemini-* id."
            ),
        };
    }

    public function forModel(string $model): AiProvider
    {
        return $this->get($this->providerNameFor($model));
    }

    /** True when at least one provider has a usable credential. */
    public function anyConfigured(): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->isConfigured()) {
                return true;
            }
        }

        return false;
    }
}
