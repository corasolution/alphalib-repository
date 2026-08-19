<?php

namespace Alpha\Journal\Oai;

use Alpha\Journal\Models\Publication;

/**
 * Maps a published Publication to unqualified Dublin Core (oai_dc — the mandatory format, §5.1).
 * jats/mods/rfc1807 are not implemented yet; ListMetadataFormats only ever advertises oai_dc,
 * which is protocol-valid on its own — never advertise a format this class can't produce.
 */
class DublinCoreMapper
{
    /** @return array<string, string[]> */
    public function map(Publication $publication): array
    {
        $submission = $publication->submission;
        $journal = $submission->journal;
        $locale = $journal->primary_locale;

        $creators = $publication->contributors
            ->map(fn ($c) => trim("{$c->family_name}, {$c->given_name}"))
            ->filter()
            ->values()
            ->all();

        $identifiers = array_values(array_filter([
            $publication->doi ? "https://doi.org/{$publication->doi}" : null,
        ]));

        $fields = [
            'title' => array_filter([$publication->title->get($locale, 'en')]),
            'creator' => $creators,
            'description' => $publication->abstract ? array_filter([$publication->abstract->get($locale, 'en')]) : [],
            'publisher' => array_filter([$journal->title->get($locale, 'en') ?? $journal->path]),
            'date' => $publication->published_at ? [$publication->published_at->toIso8601ZuluString()] : [],
            'type' => ['info:eu-repo/semantics/article'],
            'language' => [$locale],
            'identifier' => $identifiers,
        ];

        return array_filter($fields, fn ($values) => ! empty($values));
    }
}
