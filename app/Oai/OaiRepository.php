<?php

namespace Alpha\Journal\Oai;

use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Scopes\JournalScope;
use Alpha\Journal\Models\Section;
use Alpha\Journal\Oai\Exceptions\OaiException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * OAI-PMH 2.0 provider (CLAUDE.md §5.1). Only published Publications on enabled journals are ever
 * exposed — records live behind Publication.status='published', set by SubmissionWorkflow's
 * publish transition, not by Submission.stage alone.
 *
 * Deleted-record policy is declared "persistent" in Identify(), but there is no unpublish/retract
 * flow yet to actually produce a deleted record — that's real, just not reachable today.
 */
class OaiRepository
{
    public function __construct(private readonly DublinCoreMapper $mapper) {}

    /** @return array<string, string> */
    public function identify(): array
    {
        $earliest = $this->publishedPublicationsQuery()->min('published_at');

        return [
            'repositoryName' => config('app.name').' OAI-PMH Repository',
            'baseURL' => route('oai'),
            'protocolVersion' => '2.0',
            'earliestDatestamp' => $earliest ? Carbon::parse($earliest)->toIso8601ZuluString() : now()->toIso8601ZuluString(),
            'deletedRecord' => 'persistent',
            'granularity' => 'YYYY-MM-DDThh:mm:ssZ',
            'adminEmail' => config('mail.from.address') ?: 'admin@example.com',
        ];
    }

    /** @return array<int, array{prefix: string, schema: string, namespace: string}> */
    public function listMetadataFormats(?string $identifier = null): array
    {
        if ($identifier !== null) {
            $this->findPublication($identifier);
        }

        return [
            [
                'prefix' => 'oai_dc',
                'schema' => 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd',
                'namespace' => 'http://www.openarchives.org/OAI/2.0/oai_dc/',
            ],
        ];
    }

    /** @return array<int, array{setSpec: string, setName: string}> */
    public function listSets(): array
    {
        $sets = [];

        $journals = Journal::withoutGlobalScope(JournalScope::class)->where('is_enabled', true)->get();

        foreach ($journals as $journal) {
            $locale = $journal->primary_locale;
            $sets[] = [
                'setSpec' => "journal:{$journal->path}",
                'setName' => $journal->title->get($locale, 'en') ?? $journal->path,
            ];

            $sections = Section::withoutGlobalScope(JournalScope::class)->where('journal_id', $journal->id)->get();

            foreach ($sections as $section) {
                $sets[] = [
                    'setSpec' => "journal:{$journal->path}:{$section->id}",
                    'setName' => $section->title->get($locale, 'en') ?? "Section #{$section->id}",
                ];
            }
        }

        if (empty($sets)) {
            throw OaiException::noSetHierarchy();
        }

        return $sets;
    }

    /** @return array{record: OaiRecord, metadata: array<string, string[]>} */
    public function getRecord(string $identifierString, string $metadataPrefix): array
    {
        $this->guardMetadataPrefix($metadataPrefix);
        $publication = $this->findPublication($identifierString);

        return [
            'record' => $this->toRecord($publication),
            'metadata' => $this->mapper->map($publication),
        ];
    }

    /** @param array<string, string> $params */
    public function listIdentifiers(array $params): array
    {
        return $this->list($params, includeMetadata: false);
    }

    /** @param array<string, string> $params */
    public function listRecords(array $params): array
    {
        return $this->list($params, includeMetadata: true);
    }

    /** @param array<string, string> $params */
    private function list(array $params, bool $includeMetadata): array
    {
        if (isset($params['resumptionToken'])) {
            if (count(array_diff_key($params, ['resumptionToken' => null])) > 0) {
                throw OaiException::badArgument('resumptionToken must not be combined with any other argument.');
            }

            $state = ResumptionToken::resolve($params['resumptionToken']);
        } else {
            if (empty($params['metadataPrefix'])) {
                throw OaiException::badArgument('metadataPrefix is required.');
            }

            $this->guardMetadataPrefix($params['metadataPrefix']);

            $state = [
                'metadataPrefix' => $params['metadataPrefix'],
                'from' => $params['from'] ?? null,
                'until' => $params['until'] ?? null,
                'set' => $params['set'] ?? null,
                'offset' => 0,
            ];
        }

        $query = $this->publishedPublicationsQuery();
        $this->applyDateFilter($query, $state['from'], $state['until']);
        $this->applySetFilter($query, $state['set']);

        $total = (clone $query)->count();

        if ($total === 0) {
            throw OaiException::noRecordsMatch();
        }

        $page = $query->orderBy('id')->skip($state['offset'])->take(config('oai.page_size', 100))->get();

        if ($page->isEmpty()) {
            throw OaiException::noRecordsMatch();
        }

        $items = $page->map(fn (Publication $p) => [
            'record' => $this->toRecord($p),
            'metadata' => $includeMetadata ? $this->mapper->map($p) : null,
        ])->all();

        $nextOffset = $state['offset'] + $page->count();
        $resumptionToken = null;

        if ($nextOffset < $total) {
            $resumptionToken = ResumptionToken::issue([...$state, 'offset' => $nextOffset]);
        }

        return [
            'items' => $items,
            'resumptionToken' => $resumptionToken,
            'cursor' => $state['offset'],
            'completeListSize' => $total,
        ];
    }

    private function publishedPublicationsQuery(): Builder
    {
        return Publication::query()
            ->where('status', 'published')
            ->whereHas('submission', function ($q) {
                $q->withoutGlobalScope(JournalScope::class)
                    ->where('stage', SubmissionStage::Published->value)
                    ->whereHas('journal', fn ($jq) => $jq->where('is_enabled', true));
            })
            ->with(['submission.journal', 'submission.section', 'contributors']);
    }

    private function applyDateFilter(Builder $query, ?string $from, ?string $until): void
    {
        if ($from !== null) {
            $query->where('published_at', '>=', $this->parseOaiDate($from));
        }

        if ($until !== null) {
            $query->where('published_at', '<=', $this->parseOaiDate($until, endOfDay: true));
        }
    }

    private function parseOaiDate(string $date, bool $endOfDay = false): Carbon
    {
        try {
            $carbon = strlen($date) === 10
                ? Carbon::createFromFormat('Y-m-d', $date)->startOfDay()
                : Carbon::parse($date);
        } catch (Throwable) {
            throw OaiException::badArgument("Invalid date: {$date}");
        }

        return $endOfDay ? $carbon->endOfDay() : $carbon;
    }

    private function applySetFilter(Builder $query, ?string $set): void
    {
        if ($set === null) {
            return;
        }

        $parts = explode(':', $set);

        if (count($parts) < 2 || $parts[0] !== 'journal') {
            throw OaiException::noRecordsMatch("Unknown set: {$set}");
        }

        $journalPath = $parts[1];
        $sectionId = $parts[2] ?? null;

        $query->whereHas('submission', function ($q) use ($journalPath, $sectionId) {
            $q->withoutGlobalScope(JournalScope::class)
                ->whereHas('journal', fn ($jq) => $jq->where('path', $journalPath));

            if ($sectionId !== null) {
                $q->where('section_id', $sectionId);
            }
        });
    }

    private function findPublication(string $identifierString): Publication
    {
        try {
            $identifier = OaiIdentifier::parse($identifierString);
        } catch (InvalidArgumentException) {
            throw OaiException::idDoesNotExist();
        }

        $publication = $this->publishedPublicationsQuery()
            ->where('id', $identifier->publicationId)
            ->whereHas('submission', function ($q) use ($identifier) {
                $q->withoutGlobalScope(JournalScope::class)
                    ->whereHas('journal', fn ($jq) => $jq->where('path', $identifier->journalPath));
            })
            ->first();

        if ($publication === null) {
            throw OaiException::idDoesNotExist();
        }

        return $publication;
    }

    private function toRecord(Publication $publication): OaiRecord
    {
        $submission = $publication->submission;
        $journal = $submission->journal;

        $setSpecs = ["journal:{$journal->path}"];

        if ($submission->section_id !== null) {
            $setSpecs[] = "journal:{$journal->path}:{$submission->section_id}";
        }

        return new OaiRecord(
            identifier: OaiIdentifier::for($journal->path, $publication->id),
            datestamp: ($publication->published_at ?? $publication->updated_at)->toIso8601ZuluString(),
            setSpecs: $setSpecs,
            publicationId: $publication->id,
        );
    }

    private function guardMetadataPrefix(string $prefix): void
    {
        if ($prefix !== 'oai_dc') {
            throw OaiException::cannotDisseminateFormat();
        }
    }
}
