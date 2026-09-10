<?php

namespace Alpha\Journal\Ai;

use Alpha\Journal\Enums\AiFeature;
use RuntimeException;

/**
 * Prompts live in resources/ai/prompts/{feature}/v{n}.md, never inline in PHP (CLAUDE.md §6
 * guardrail 4). Versioning is the point: ai_runs records which version produced each output, so
 * when a prompt is revised you can tell whether human acceptance actually improved or you just
 * changed the wording and hoped.
 */
class PromptRegistry
{
    public function __construct(private readonly ?string $basePath = null) {}

    /** @return array{version: string, body: string} */
    public function resolve(AiFeature $feature, ?int $version = null): array
    {
        $version ??= $this->latestVersion($feature);
        $path = $this->pathFor($feature, $version);

        if (! is_file($path)) {
            throw new RuntimeException("No prompt v{$version} for AI feature [{$feature->value}] at {$path}.");
        }

        return ['version' => "v{$version}", 'body' => trim((string) file_get_contents($path))];
    }

    public function latestVersion(AiFeature $feature): int
    {
        $versions = $this->availableVersions($feature);

        if ($versions === []) {
            throw new RuntimeException("No prompts registered for AI feature [{$feature->value}].");
        }

        return max($versions);
    }

    /** @return list<int> */
    public function availableVersions(AiFeature $feature): array
    {
        $files = glob($this->directoryFor($feature).'/v*.md') ?: [];

        // Sorted numerically, not by filename: glob() returns v10 before v9 lexically, which
        // would quietly pin the registry to an old prompt once versions reach double digits.
        return array_values(array_filter(array_map(
            fn (string $file) => preg_match('/^v(\d+)\.md$/', basename($file), $m) ? (int) $m[1] : null,
            $files,
        ), fn (?int $v) => $v !== null));
    }

    private function directoryFor(AiFeature $feature): string
    {
        return ($this->basePath ?? resource_path('ai/prompts')).'/'.$feature->value;
    }

    private function pathFor(AiFeature $feature, int $version): string
    {
        return $this->directoryFor($feature)."/v{$version}.md";
    }
}
