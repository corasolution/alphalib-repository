<?php

namespace Alpha\Journal\Mail;

use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\JournalEmailTemplate;
use Alpha\Journal\Models\Scopes\JournalScope;
use RuntimeException;

/**
 * Per-journal, per-locale editable templates with variable substitution (CLAUDE.md §11.1).
 * Journal overrides live in journal_email_templates; the shipped default for every key lives in
 * resources/mail/templates/{key}/{locale}.md, mirroring the AI prompt registry pattern (§6.4) —
 * versioned content files, never inline in PHP.
 */
class EmailTemplateRenderer
{
    public function __construct(private readonly string $basePath = '') {}

    private function basePath(): string
    {
        return $this->basePath ?: resource_path('mail/templates');
    }

    /**
     * Returns null when the journal has explicitly disabled this template — the caller must not
     * send anything in that case.
     *
     * @param  array<string, string>  $variables
     */
    public function render(Journal $journal, string $key, string $locale, array $variables = []): ?RenderedEmailTemplate
    {
        $override = JournalEmailTemplate::withoutGlobalScope(JournalScope::class)
            ->where('journal_id', $journal->id)
            ->where('key', $key)
            ->where('locale', $locale)
            ->first();

        if ($override && ! $override->is_enabled) {
            return null;
        }

        $default = $this->loadDefault($key, $locale);

        $subject = $override?->subject ?? $default['subject'];
        $body = $override?->body ?? $default['body'];

        return new RenderedEmailTemplate(
            subject: $this->substitute($subject, $variables),
            body: $this->substitute($body, $variables),
        );
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function loadDefault(string $key, string $locale): array
    {
        $path = "{$this->basePath()}/{$key}/{$locale}.md";

        if (! is_file($path)) {
            $path = "{$this->basePath()}/{$key}/en.md";
        }

        if (! is_file($path)) {
            throw new RuntimeException("No default email template registered for key [{$key}].");
        }

        $contents = file_get_contents($path);

        if (! preg_match('/^Subject:\s*([^\r\n]+)\r?\n\r?\n(.*)$/s', $contents, $matches)) {
            throw new RuntimeException("Malformed email template [{$path}]: expected 'Subject: ...' on the first line.");
        }

        return ['subject' => trim($matches[1]), 'body' => trim($matches[2])];
    }

    /** @param array<string, string> $variables */
    private function substitute(string $text, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $name => $value) {
            $replacements["{{{$name}}}"] = $value;
        }

        return strtr($text, $replacements);
    }
}
