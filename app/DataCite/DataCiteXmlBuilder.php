<?php

namespace Alpha\Journal\DataCite;

use Alpha\Journal\Models\Publication;
use DOMDocument;
use DOMElement;

/**
 * DataCite Metadata Schema 4.x (CLAUDE.md §5.5, §6). Same DOMDocument-based approach as the OAI-PMH
 * and Crossref builders — text content needs real XML escaping.
 */
class DataCiteXmlBuilder
{
    public function build(Publication $publication, string $doi): string
    {
        $submission = $publication->submission;
        $journal = $submission->journal;
        $locale = $journal->primary_locale;

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $resource = $doc->createElementNS('http://datacite.org/schema/kernel-4', 'resource');
        $resource->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $resource->setAttribute(
            'xsi:schemaLocation',
            'http://datacite.org/schema/kernel-4 http://schema.datacite.org/meta/kernel-4/metadata.xsd'
        );
        $doc->appendChild($resource);

        $identifier = $this->el($doc, 'identifier', $doi);
        $identifier->setAttribute('identifierType', 'DOI');
        $resource->appendChild($identifier);

        $resource->appendChild($this->buildCreators($doc, $publication));
        $resource->appendChild($this->buildTitles($doc, $publication, $locale));

        $resource->appendChild($this->el($doc, 'publisher', $journal->title->get($locale, 'en') ?? $journal->path));
        $resource->appendChild($this->el($doc, 'publicationYear', $publication->published_at?->format('Y') ?? (string) now()->format('Y')));

        $resourceType = $this->el($doc, 'resourceType', 'JournalArticle');
        $resourceType->setAttribute('resourceTypeGeneral', 'Text');
        $resource->appendChild($resourceType);

        if ($publication->abstract && $publication->abstract->get($locale, 'en')) {
            $descriptions = $doc->createElement('descriptions');
            $description = $this->el($doc, 'description', $publication->abstract->get($locale, 'en'));
            $description->setAttribute('descriptionType', 'Abstract');
            $descriptions->appendChild($description);
            $resource->appendChild($descriptions);
        }

        $languageEl = $doc->createElement('language');
        $languageEl->appendChild($doc->createTextNode($locale));
        $resource->appendChild($languageEl);

        return $doc->saveXML();
    }

    private function buildCreators(DOMDocument $doc, Publication $publication): DOMElement
    {
        $creators = $doc->createElement('creators');

        foreach ($publication->contributors as $contributor) {
            $creator = $doc->createElement('creator');

            $creatorName = $this->el($doc, 'creatorName', "{$contributor->family_name}, {$contributor->given_name}");
            $creatorName->setAttribute('nameType', 'Personal');
            $creator->appendChild($creatorName);

            $creator->appendChild($this->el($doc, 'givenName', $contributor->given_name));
            $creator->appendChild($this->el($doc, 'familyName', $contributor->family_name));

            if ($contributor->orcid) {
                $nameId = $this->el($doc, 'nameIdentifier', "https://orcid.org/{$contributor->orcid}");
                $nameId->setAttribute('nameIdentifierScheme', 'ORCID');
                $nameId->setAttribute('schemeURI', 'https://orcid.org');
                $creator->appendChild($nameId);
            }

            $creators->appendChild($creator);
        }

        return $creators;
    }

    private function buildTitles(DOMDocument $doc, Publication $publication, string $locale): DOMElement
    {
        $titles = $doc->createElement('titles');
        $titles->appendChild($this->el($doc, 'title', $publication->title->get($locale, 'en') ?? ''));

        return $titles;
    }

    private function el(DOMDocument $doc, string $name, string $value): DOMElement
    {
        $el = $doc->createElement($name);
        $el->appendChild($doc->createTextNode($value));

        return $el;
    }
}
