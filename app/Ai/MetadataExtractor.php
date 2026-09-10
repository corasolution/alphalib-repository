<?php

namespace Alpha\Journal\Ai;

use Alpha\Journal\Enums\AiFeature;
use Alpha\Journal\Models\AiRun;
use Alpha\Journal\Models\Journal;

/**
 * A1 (CLAUDE.md §6): manuscript in, proposed submission metadata out, so the author confirms and
 * corrects rather than typing from scratch (§13.1). Cuts submission from ~20 min to ~4.
 *
 * The output of this class is a SUGGESTION. Nothing here writes to a submission — the analyze
 * endpoint hands it to the browser to pre-fill a form the author still submits themselves.
 */
class MetadataExtractor
{
    public function __construct(
        private readonly AiRunner $runner,
        private readonly DocumentTextExtractor $text,
    ) {}

    /**
     * @return array{run: AiRun, metadata: array<string, mixed>|null}
     */
    public function extract(Journal $journal, string $absolutePath, string $originalName): array
    {
        $body = $this->text->extract($absolutePath, $originalName);

        $result = $this->runner->run(
            feature: AiFeature::MetadataExtraction,
            journal: $journal,
            userContent: "Manuscript filename: {$originalName}\n\n---\n\n".$body,
            toolName: 'extracted_metadata',
            schema: self::schema(),
        );

        return [
            'run' => $result['run'],
            'metadata' => $result['data'] === null ? null : $this->normalise($result['data']),
        ];
    }

    /**
     * Every field is nullable by design. "Not stated in this manuscript" is a normal, frequent
     * answer, and the prompt is explicit that a null beats an invented value — the schema has to
     * permit that or the model is pushed into fabricating to satisfy it.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => ['string', 'null'], 'description' => 'The manuscript title, verbatim.'],
                'abstract' => ['type' => ['string', 'null'], 'description' => 'The abstract section, verbatim. Null if the manuscript has none.'],
                'keywords' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Keywords as printed. Empty array if none are listed.',
                ],
                'authors' => [
                    'type' => 'array',
                    'description' => 'The paper\'s authors, in printed order.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'given_name' => ['type' => ['string', 'null']],
                            'family_name' => ['type' => ['string', 'null']],
                            'affiliation' => ['type' => ['string', 'null']],
                            'email' => ['type' => ['string', 'null']],
                            'orcid' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['family_name'],
                    ],
                ],
                'language' => ['type' => ['string', 'null'], 'description' => 'ISO 639-1 code of the body text, e.g. en or km.'],
                'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                'extraction_notes' => ['type' => ['string', 'null'], 'description' => 'Anything the author should know about what could not be read.'],
            ],
            'required' => ['title', 'abstract', 'keywords', 'authors', 'confidence'],
        ];
    }

    /**
     * Defensive shaping. Even with a schema, the returned structure is model output, and this
     * value goes straight into a form the author sees — so types are coerced here rather than
     * trusted, and empty strings collapse to null so the UI's "was anything suggested?" check
     * doesn't light up for a field the model actually left blank.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        $string = fn (mixed $v) => is_string($v) && trim($v) !== '' ? trim($v) : null;

        $authors = collect(is_array($data['authors'] ?? null) ? $data['authors'] : [])
            ->filter(fn ($a) => is_array($a))
            ->map(fn (array $a) => [
                'given_name' => $string($a['given_name'] ?? null),
                'family_name' => $string($a['family_name'] ?? null),
                'affiliation' => $string($a['affiliation'] ?? null),
                'email' => $string($a['email'] ?? null),
                'orcid' => $string($a['orcid'] ?? null),
            ])
            ->filter(fn (array $a) => $a['family_name'] !== null || $a['given_name'] !== null)
            ->values()
            ->all();

        return [
            'title' => $string($data['title'] ?? null),
            'abstract' => $string($data['abstract'] ?? null),
            'keywords' => collect(is_array($data['keywords'] ?? null) ? $data['keywords'] : [])
                ->map(fn ($k) => $string($k))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'authors' => $authors,
            'language' => $string($data['language'] ?? null),
            'confidence' => in_array($data['confidence'] ?? null, ['high', 'medium', 'low'], true)
                ? $data['confidence']
                : 'low',
            'extraction_notes' => $string($data['extraction_notes'] ?? null),
        ];
    }
}
