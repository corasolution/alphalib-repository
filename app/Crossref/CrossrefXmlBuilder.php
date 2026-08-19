<?php

namespace Alpha\Journal\Crossref;

use Alpha\Journal\Models\Publication;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;

/**
 * Crossref deposit XML, schema 5.3.x, journal_article type (CLAUDE.md §5.2). Built with
 * DOMDocument for the same reason as the OAI-PMH provider — text content (titles, abstracts)
 * needs real escaping, not string interpolation.
 *
 * The <resource> URL points at the eventual public article landing page
 * (/journal/{path}/articles/{id}), which does not exist yet — that's P4. The field is mandatory
 * in the Crossref schema, so it's included now with the intended path; deposits should not
 * actually be submitted to production Crossref until that route is real and resolves.
 */
class CrossrefXmlBuilder
{
    public function build(Publication $publication, string $doi): string
    {
        $submission = $publication->submission;
        $journal = $submission->journal;
        $issue = $submission->issue;
        $locale = $journal->primary_locale;

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $batch = $doc->createElementNS('http://www.crossref.org/schema/5.3.1', 'doi_batch');
        $batch->setAttribute('version', '5.3.1');
        $batch->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:jats', 'http://www.ncbi.nlm.nih.gov/JATS1');
        $doc->appendChild($batch);

        $batch->appendChild($this->buildHead($doc, $journal));

        $body = $doc->createElement('body');
        $journalEl = $doc->createElement('journal');

        $journalMeta = $doc->createElement('journal_metadata');
        $journalMeta->appendChild($this->el($doc, 'full_title', $journal->title->get($locale, 'en') ?? $journal->path));
        $journalEl->appendChild($journalMeta);

        if ($issue !== null && ($issue->volume !== null || $issue->number !== null)) {
            $journalEl->appendChild($this->buildJournalIssue($doc, $issue));
        }

        $journalEl->appendChild($this->buildArticle($doc, $publication, $doi, $locale));
        $body->appendChild($journalEl);
        $batch->appendChild($body);

        return $doc->saveXML();
    }

    private function buildHead(DOMDocument $doc, $journal): DOMElement
    {
        $head = $doc->createElement('head');
        $head->appendChild($this->el($doc, 'doi_batch_id', (string) Str::uuid()));
        $head->appendChild($this->el($doc, 'timestamp', (string) now()->format('YmdHis')));

        $depositor = $doc->createElement('depositor');
        $depositor->appendChild($this->el($doc, 'depositor_name', config('services.crossref.depositor_name') ?? 'Alpha Journal'));
        $depositor->appendChild($this->el($doc, 'email_address', config('services.crossref.depositor_email') ?? 'admin@example.com'));
        $head->appendChild($depositor);

        $head->appendChild($this->el($doc, 'registrant', $journal->title->get($journal->primary_locale, 'en') ?? $journal->path));

        return $head;
    }

    private function buildJournalIssue(DOMDocument $doc, $issue): DOMElement
    {
        $issueEl = $doc->createElement('journal_issue');

        if ($issue->year !== null) {
            $pubDate = $doc->createElement('publication_date');
            $pubDate->appendChild($this->el($doc, 'year', (string) $issue->year));
            $issueEl->appendChild($pubDate);
        }

        if ($issue->volume !== null) {
            $volumeEl = $doc->createElement('journal_volume');
            $volumeEl->appendChild($this->el($doc, 'volume', (string) $issue->volume));
            $issueEl->appendChild($volumeEl);
        }

        if ($issue->number !== null) {
            $issueEl->appendChild($this->el($doc, 'issue', (string) $issue->number));
        }

        return $issueEl;
    }

    private function buildArticle(DOMDocument $doc, Publication $publication, string $doi, string $locale): DOMElement
    {
        $article = $doc->createElement('journal_article');
        $article->setAttribute('publication_type', 'full_text');

        $titles = $doc->createElement('titles');
        $titles->appendChild($this->el($doc, 'title', $publication->title->get($locale, 'en') ?? ''));
        $article->appendChild($titles);

        $contributors = $publication->contributors;
        if ($contributors->isNotEmpty()) {
            $contributorsEl = $doc->createElement('contributors');
            foreach ($contributors as $index => $contributor) {
                $person = $doc->createElement('person_name');
                $person->setAttribute('sequence', $index === 0 ? 'first' : 'additional');
                $person->setAttribute('contributor_role', 'author');
                $person->appendChild($this->el($doc, 'given_name', $contributor->given_name));
                $person->appendChild($this->el($doc, 'surname', $contributor->family_name));

                if ($contributor->orcid) {
                    $person->appendChild($this->el($doc, 'ORCID', "https://orcid.org/{$contributor->orcid}"));
                }

                $contributorsEl->appendChild($person);
            }
            $article->appendChild($contributorsEl);
        }

        if ($publication->abstract && $publication->abstract->get($locale, 'en')) {
            $abstract = $doc->createElementNS('http://www.ncbi.nlm.nih.gov/JATS1', 'jats:abstract');
            $p = $doc->createElementNS('http://www.ncbi.nlm.nih.gov/JATS1', 'jats:p');
            $p->appendChild($doc->createTextNode($publication->abstract->get($locale, 'en')));
            $abstract->appendChild($p);
            $article->appendChild($abstract);
        }

        if ($publication->published_at !== null) {
            $pubDate = $doc->createElement('publication_date');
            $pubDate->appendChild($this->el($doc, 'year', $publication->published_at->format('Y')));
            $pubDate->appendChild($this->el($doc, 'month', $publication->published_at->format('m')));
            $pubDate->appendChild($this->el($doc, 'day', $publication->published_at->format('d')));
            $article->appendChild($pubDate);
        }

        $doiData = $doc->createElement('doi_data');
        $doiData->appendChild($this->el($doc, 'doi', $doi));
        $journal = $publication->submission->journal;
        $doiData->appendChild($this->el(
            $doc,
            'resource',
            rtrim(config('app.url'), '/')."/journal/{$journal->path}/articles/{$publication->id}"
        ));
        $article->appendChild($doiData);

        return $article;
    }

    private function el(DOMDocument $doc, string $name, string $value): DOMElement
    {
        $el = $doc->createElement($name);
        $el->appendChild($doc->createTextNode($value));

        return $el;
    }
}
