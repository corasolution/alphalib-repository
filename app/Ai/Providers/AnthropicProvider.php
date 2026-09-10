<?php

namespace Alpha\Journal\Ai\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Anthropic Messages API.
 *
 * Structured output comes from tool use with a forced `tool_choice`, not from asking for JSON in
 * the prompt and parsing prose. Prose parsing fails open — a chatty preamble becomes a malformed
 * record silently pre-filling an author's submission form — whereas a forced tool call either
 * conforms to the schema or is absent, which this class treats as a failure.
 */
class AnthropicProvider implements AiProvider
{
    public function __construct(private readonly AiCredentials $credentials) {}

    public function name(): string
    {
        return 'anthropic';
    }

    public function isConfigured(): bool
    {
        return $this->credentials->has($this->name());
    }

    public function structured(string $model, string $system, string $userContent, string $toolName, array $schema): array
    {
        $response = $this->client()->post($this->endpoint(), [
            'model' => $model,
            'max_tokens' => 4096,
            'system' => $system,
            // Forces the model to answer through the schema instead of choosing to reply in prose.
            'tool_choice' => ['type' => 'tool', 'name' => $toolName],
            'tools' => [[
                'name' => $toolName,
                'description' => 'Return the extracted values in this exact structure.',
                'input_schema' => $schema,
            ]],
            'messages' => [['role' => 'user', 'content' => $userContent]],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Anthropic API returned {$response->status()}: ".mb_substr($response->body(), 0, 500)
            );
        }

        $body = $response->json();

        return [
            'data' => $this->extractToolInput($body, $toolName),
            'input_tokens' => (int) data_get($body, 'usage.input_tokens', 0),
            'output_tokens' => (int) data_get($body, 'usage.output_tokens', 0),
        ];
    }

    public function ping(): void
    {
        $response = $this->client()->post($this->endpoint(), [
            'model' => config('ai.default_model'),
            'max_tokens' => 1,
            'messages' => [['role' => 'user', 'content' => 'ping']],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Anthropic API returned {$response->status()}: ".mb_substr($response->body(), 0, 300)
            );
        }
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.anthropic.base_url'), '/').'/v1/messages';
    }

    private function client(): PendingRequest
    {
        $key = $this->credentials->for($this->name());

        if ($key === null) {
            throw new RuntimeException('No Anthropic API key is configured.');
        }

        return Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout((int) config('ai.timeout'))
            // Retries 429 and 5xx only. A 400 means the request itself is wrong, and sending it
            // again three times just spends money reproducing the same error.
            ->retry((int) config('ai.retries'), 1000, function ($exception) {
                $status = $exception instanceof RequestException ? $exception->response->status() : null;

                return $status === null || $status === 429 || $status >= 500;
            }, throw: false);
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function extractToolInput(?array $body, string $toolName): array
    {
        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === $toolName) {
                return is_array($block['input'] ?? null) ? $block['input'] : [];
            }
        }

        throw new RuntimeException("Anthropic response contained no [{$toolName}] tool call.");
    }
}
