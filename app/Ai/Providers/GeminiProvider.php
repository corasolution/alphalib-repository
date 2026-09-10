<?php

namespace Alpha\Journal\Ai\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Gemini (Generative Language API).
 *
 * Uses `responseSchema` with a JSON response type rather than function calling. Gemini supports
 * both, but constrained decoding against a schema is the more reliable of the two for extraction,
 * and it keeps this class's contract identical to the Anthropic one: conform to the shape or fail.
 * `$toolName` is therefore accepted and ignored — it is Anthropic vocabulary that the interface
 * carries, not something Gemini needs.
 */
class GeminiProvider implements AiProvider
{
    public function __construct(private readonly AiCredentials $credentials) {}

    public function name(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return $this->credentials->has($this->name());
    }

    public function structured(string $model, string $system, string $userContent, string $toolName, array $schema): array
    {
        $response = $this->client()->post($this->endpoint($model, 'generateContent'), [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $userContent]]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => self::toGeminiSchema($schema),
                'maxOutputTokens' => 4096,
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Gemini API returned {$response->status()}: ".mb_substr($response->body(), 0, 500)
            );
        }

        $body = $response->json();
        $text = data_get($body, 'candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            // Usually a safety block or a truncated response; either way there is nothing to
            // parse, and treating it as an empty extraction would look like a manuscript that
            // genuinely had no metadata.
            $reason = data_get($body, 'candidates.0.finishReason', 'unknown');
            throw new RuntimeException("Gemini returned no content (finishReason: {$reason}).");
        }

        $data = json_decode($text, true);

        if (! is_array($data)) {
            throw new RuntimeException('Gemini returned a response that was not valid JSON.');
        }

        return [
            'data' => $data,
            'input_tokens' => (int) data_get($body, 'usageMetadata.promptTokenCount', 0),
            'output_tokens' => (int) data_get($body, 'usageMetadata.candidatesTokenCount', 0),
        ];
    }

    public function ping(): void
    {
        $response = $this->client()->post($this->endpoint(config('ai.default_gemini_model'), 'generateContent'), [
            'contents' => [['role' => 'user', 'parts' => [['text' => 'ping']]]],
            'generationConfig' => ['maxOutputTokens' => 1],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Gemini API returned {$response->status()}: ".mb_substr($response->body(), 0, 300)
            );
        }
    }

    /**
     * Translates the JSON Schema this app writes into the OpenAPI 3.0 subset Gemini accepts.
     *
     * The load-bearing difference is nullability. This app writes `type: ["string", "null"]`,
     * which Anthropic accepts and Gemini rejects outright — Gemini expects a single `type` plus
     * `nullable: true`. Every optional field in the A1 extraction schema is written that way
     * (deliberately, so the model can answer "not stated" instead of inventing a value), so
     * without this translation the entire feature 400s on Gemini.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function toGeminiSchema(array $schema): array
    {
        $out = [];

        foreach ($schema as $key => $value) {
            if ($key === 'type' && is_array($value)) {
                $types = array_values(array_filter($value, fn ($t) => $t !== 'null'));
                $out['type'] = $types[0] ?? 'string';

                if (count($types) !== count($value)) {
                    $out['nullable'] = true;
                }

                continue;
            }

            $out[$key] = match (true) {
                $key === 'properties' && is_array($value) => array_map(
                    fn ($property) => is_array($property) ? self::toGeminiSchema($property) : $property,
                    $value,
                ),
                $key === 'items' && is_array($value) => self::toGeminiSchema($value),
                default => $value,
            };
        }

        return $out;
    }

    private function endpoint(string $model, string $method): string
    {
        return rtrim((string) config('services.gemini.base_url'), '/')."/v1beta/models/{$model}:{$method}";
    }

    private function client(): PendingRequest
    {
        $key = $this->credentials->for($this->name());

        if ($key === null) {
            throw new RuntimeException('No Gemini API key is configured.');
        }

        // Header rather than the ?key= query parameter: a key in a URL ends up in access logs,
        // proxy logs and exception traces, and this one is billable.
        return Http::withHeaders([
            'x-goog-api-key' => $key,
            'content-type' => 'application/json',
        ])
            ->timeout((int) config('ai.timeout'))
            ->retry((int) config('ai.retries'), 1000, function ($exception) {
                $status = $exception instanceof RequestException ? $exception->response->status() : null;

                return $status === null || $status === 429 || $status >= 500;
            }, throw: false);
    }
}
