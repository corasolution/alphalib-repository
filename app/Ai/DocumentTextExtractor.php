<?php

namespace Alpha\Journal\Ai;

use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

/**
 * Manuscript file -> plain text, so a model can read it. Deliberately dependency-light: DOCX is
 * a ZIP containing XML, which ZipArchive (already used by Preservation\BagBuilder) opens without
 * adding a library. Only PDF needs one.
 *
 * This does not attempt layout fidelity — it feeds an extraction prompt, not a typesetter. That
 * is also why it never throws on a partially-readable file: some text is enough to pre-fill a
 * form, and the author confirms everything anyway.
 */
class DocumentTextExtractor
{
    public function extract(string $absolutePath, ?string $originalName = null): string
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("Cannot read manuscript at {$absolutePath}.");
        }

        return match ($this->kind($originalName ?? $absolutePath)) {
            'pdf' => $this->fromPdf($absolutePath),
            'docx' => $this->fromDocx($absolutePath),
            default => throw new RuntimeException(
                'Only PDF and DOCX manuscripts can be read automatically. Legacy .doc and .odt '
                .'are accepted as submissions but are not extractable.'
            ),
        };
    }

    public function canExtract(string $originalName): bool
    {
        return in_array($this->kind($originalName), ['pdf', 'docx'], true);
    }

    private function kind(string $name): string
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    private function fromPdf(string $path): string
    {
        try {
            return $this->normalise((new PdfParser)->parseFile($path)->getText());
        } catch (\Throwable $e) {
            // A scanned (image-only) PDF legitimately yields nothing. That is a real, common
            // state for manuscripts from the region, not an error worth failing a submission over.
            throw new RuntimeException('Could not read text from this PDF. It may be a scan rather than a text document.', 0, $e);
        }
    }

    private function fromDocx(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open this DOCX file.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('This DOCX file has no readable document body.');
        }

        // Paragraph and line-break tags become newlines BEFORE tags are stripped — otherwise the
        // whole manuscript collapses into one line and the model loses the structural cue that
        // tells a title from a heading from body text.
        $xml = preg_replace('/<w:(p|br|tab)\b[^>]*\/?>/', "\n", $xml) ?? $xml;

        return $this->normalise(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    private function normalise(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
