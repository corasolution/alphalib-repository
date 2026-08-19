<?php

namespace Alpha\Journal\Http\Controllers;

use Alpha\Journal\Oai\Exceptions\OaiException;
use Alpha\Journal\Oai\OaiRecord;
use Alpha\Journal\Oai\OaiRepository;
use DOMDocument;
use DOMElement;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * OAI-PMH 2.0 provider endpoint (CLAUDE.md §5.1). XML is built with DOMDocument, not a Blade
 * view — string-templated XML is an injection risk the moment a title or abstract contains `<`
 * or `&`; DOMDocument escapes text nodes for us.
 */
class OaiController extends Controller
{
    public function handle(Request $request, OaiRepository $repository): Response
    {
        $verb = $request->query('verb');

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElementNS('http://www.openarchives.org/OAI/2.0/', 'OAI-PMH');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttribute(
            'xsi:schemaLocation',
            'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd'
        );
        $doc->appendChild($root);

        $root->appendChild($this->el($doc, 'responseDate', now()->toIso8601ZuluString()));

        $requestEl = $doc->createElement('request', route('oai'));
        $root->appendChild($requestEl);

        try {
            if ($verb === null) {
                throw OaiException::badVerb('The request is missing the required verb argument.');
            }

            $requestEl->setAttribute('verb', $verb);
            foreach (['identifier', 'metadataPrefix', 'from', 'until', 'set', 'resumptionToken'] as $param) {
                if ($request->filled($param)) {
                    $requestEl->setAttribute($param, $request->query($param));
                }
            }

            $this->dispatch($doc, $root, $verb, $request, $repository);
        } catch (OaiException $e) {
            $error = $this->el($doc, 'error', $e->getMessage());
            $error->setAttribute('code', $e->errorCode);
            $root->appendChild($error);
        }

        return response($doc->saveXML(), 200)->header('Content-Type', 'text/xml; charset=UTF-8');
    }

    private function dispatch(DOMDocument $doc, DOMElement $root, string $verb, Request $request, OaiRepository $repo): void
    {
        match ($verb) {
            'Identify' => $this->renderIdentify($doc, $root, $repo),
            'ListMetadataFormats' => $this->renderListMetadataFormats($doc, $root, $repo, $request),
            'ListSets' => $this->renderListSets($doc, $root, $repo),
            'GetRecord' => $this->renderGetRecord($doc, $root, $repo, $request),
            'ListIdentifiers' => $this->renderList($doc, $root, $repo, $request, includeMetadata: false),
            'ListRecords' => $this->renderList($doc, $root, $repo, $request, includeMetadata: true),
            default => throw OaiException::badVerb("Illegal OAI verb: {$verb}"),
        };
    }

    private function renderIdentify(DOMDocument $doc, DOMElement $root, OaiRepository $repo): void
    {
        $identify = $doc->createElement('Identify');
        foreach ($repo->identify() as $tag => $value) {
            $identify->appendChild($this->el($doc, $tag, $value));
        }
        $root->appendChild($identify);
    }

    private function renderListMetadataFormats(DOMDocument $doc, DOMElement $root, OaiRepository $repo, Request $request): void
    {
        $formats = $repo->listMetadataFormats($request->query('identifier'));

        $wrapper = $doc->createElement('ListMetadataFormats');
        foreach ($formats as $format) {
            $mf = $doc->createElement('metadataFormat');
            $mf->appendChild($this->el($doc, 'metadataPrefix', $format['prefix']));
            $mf->appendChild($this->el($doc, 'schema', $format['schema']));
            $mf->appendChild($this->el($doc, 'metadataNamespace', $format['namespace']));
            $wrapper->appendChild($mf);
        }
        $root->appendChild($wrapper);
    }

    private function renderListSets(DOMDocument $doc, DOMElement $root, OaiRepository $repo): void
    {
        $wrapper = $doc->createElement('ListSets');
        foreach ($repo->listSets() as $set) {
            $setEl = $doc->createElement('set');
            $setEl->appendChild($this->el($doc, 'setSpec', $set['setSpec']));
            $setEl->appendChild($this->el($doc, 'setName', $set['setName']));
            $wrapper->appendChild($setEl);
        }
        $root->appendChild($wrapper);
    }

    private function renderGetRecord(DOMDocument $doc, DOMElement $root, OaiRepository $repo, Request $request): void
    {
        $identifier = $request->query('identifier');
        $metadataPrefix = $request->query('metadataPrefix');

        if (! $identifier || ! $metadataPrefix) {
            throw OaiException::badArgument('GetRecord requires identifier and metadataPrefix.');
        }

        $result = $repo->getRecord($identifier, $metadataPrefix);

        $wrapper = $doc->createElement('GetRecord');
        $wrapper->appendChild($this->buildRecordElement($doc, $result['record'], $result['metadata']));
        $root->appendChild($wrapper);
    }

    private function renderList(DOMDocument $doc, DOMElement $root, OaiRepository $repo, Request $request, bool $includeMetadata): void
    {
        $params = $request->only(['metadataPrefix', 'from', 'until', 'set', 'resumptionToken']);
        $result = $includeMetadata ? $repo->listRecords($params) : $repo->listIdentifiers($params);

        $tag = $includeMetadata ? 'ListRecords' : 'ListIdentifiers';
        $itemTag = $includeMetadata ? null : 'header';

        $wrapper = $doc->createElement($tag);
        foreach ($result['items'] as $item) {
            if ($includeMetadata) {
                $wrapper->appendChild($this->buildRecordElement($doc, $item['record'], $item['metadata']));
            } else {
                $wrapper->appendChild($this->buildHeaderElement($doc, $item['record']));
            }
        }

        if ($result['resumptionToken'] !== null) {
            $tokenEl = $this->el($doc, 'resumptionToken', $result['resumptionToken']);
            $tokenEl->setAttribute('completeListSize', (string) $result['completeListSize']);
            $tokenEl->setAttribute('cursor', (string) $result['cursor']);
            $wrapper->appendChild($tokenEl);
        } elseif ($result['completeListSize'] > 0) {
            // Empty resumptionToken signals "this was the final page" once pagination has begun.
            $wrapper->appendChild($this->el($doc, 'resumptionToken', ''));
        }

        $root->appendChild($wrapper);
    }

    private function buildRecordElement(DOMDocument $doc, OaiRecord $record, array $metadata): DOMElement
    {
        $recordEl = $doc->createElement('record');
        $recordEl->appendChild($this->buildHeaderElement($doc, $record));

        $metadataEl = $doc->createElement('metadata');
        $dc = $doc->createElementNS('http://www.openarchives.org/OAI/2.0/oai_dc/', 'oai_dc:dc');
        $dc->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $dc->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $dc->setAttribute(
            'xsi:schemaLocation',
            'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd'
        );

        foreach ($metadata as $field => $values) {
            foreach ($values as $value) {
                $fieldEl = $doc->createElementNS('http://purl.org/dc/elements/1.1/', "dc:{$field}");
                $fieldEl->appendChild($doc->createTextNode($value));
                $dc->appendChild($fieldEl);
            }
        }

        $metadataEl->appendChild($dc);
        $recordEl->appendChild($metadataEl);

        return $recordEl;
    }

    private function buildHeaderElement(DOMDocument $doc, OaiRecord $record): DOMElement
    {
        $header = $doc->createElement('header');
        $header->appendChild($this->el($doc, 'identifier', $record->identifier->toString()));
        $header->appendChild($this->el($doc, 'datestamp', $record->datestamp));

        foreach ($record->setSpecs as $setSpec) {
            $header->appendChild($this->el($doc, 'setSpec', $setSpec));
        }

        return $header;
    }

    private function el(DOMDocument $doc, string $name, string $value): DOMElement
    {
        $el = $doc->createElement($name);
        $el->appendChild($doc->createTextNode($value));

        return $el;
    }
}
