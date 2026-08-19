<?php

namespace Corasoft\AlphaScholar\Ai;

use RuntimeException;

/**
 * Loads versioned prompts from resources/ai/prompts/{feature}/v{n}.md — never inline in PHP (CLAUDE.md §6.4).
 */
class PromptRegistry
{
    public function __construct(private readonly string $basePath) {}

    public function latestVersion(string $feature): int
    {
        $dir = "{$this->basePath}/{$feature}";

        if (! is_dir($dir)) {
            throw new RuntimeException("No prompts registered for feature [{$feature}].");
        }

        $versions = [];
        foreach (glob("{$dir}/v*.md") as $file) {
            if (preg_match('/v(\d+)\.md$/', $file, $m)) {
                $versions[] = (int) $m[1];
            }
        }

        if ($versions === []) {
            throw new RuntimeException("No prompt versions found for feature [{$feature}].");
        }

        return max($versions);
    }

    public function get(string $feature, ?int $version = null): string
    {
        $version ??= $this->latestVersion($feature);
        $path = "{$this->basePath}/{$feature}/v{$version}.md";

        if (! is_file($path)) {
            throw new RuntimeException("Prompt not found: {$path}");
        }

        return file_get_contents($path);
    }
}
