<?php

namespace Tests\Fixtures;

use ZipArchive;

/**
 * Builds real manuscript files on disk for the extraction tests. Generated rather than committed
 * as binaries so the fixture's content is visible in the diff when a test's expectation changes —
 * an opaque .docx in the repo is a test you can't review.
 */
class ManuscriptFixtures
{
    /**
     * A structurally genuine DOCX — a real OOXML package, not just a ZIP with a document.xml in it.
     *
     * The layout matters and is not decoration: libmagic identifies a DOCX by finding
     * [Content_Types].xml as the FIRST entry and STORED (uncompressed), with the relationship part
     * alongside it. Get that wrong and the file is sniffed as application/zip, which Laravel's
     * `mimes:docx` rule then rejects — so a fixture built the lazy way tests a file the real
     * upload endpoint would never accept.
     */
    public static function docx(string $path, array $paragraphs): string
    {
        $body = collect($paragraphs)
            ->map(fn (string $p) => '<w:p><w:r><w:t>'.htmlspecialchars($p, ENT_XML1).'</w:t></w:r></w:p>')
            ->implode('');

        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            ."<w:body>{$body}</w:body></w:document>";

        @unlink($path);

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>');
        $zip->setCompressionName('[Content_Types].xml', ZipArchive::CM_STORE);

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Target="word/document.xml" '
            .'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"/>'
            .'</Relationships>');

        $zip->addFromString('word/document.xml', $document);
        $zip->close();

        return $path;
    }

    /**
     * A genuine ODT — an accepted submission format that this app cannot pre-fill from, so it
     * exercises the "valid but not extractable" branch.
     *
     * ODT chose .odt over .doc for a practical reason: a convincing legacy .doc needs a real OLE2
     * compound-file structure (the bare magic bytes sniff as application/x-ole-storage and fail
     * `mimes:doc`), whereas ODF only asks that `mimetype` be the first entry and STORED — the same
     * shape the DOCX builder above already relies on.
     */
    public static function odt(string $path): string
    {
        @unlink($path);

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
        $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
        $zip->addFromString('content.xml',
            '<?xml version="1.0" encoding="UTF-8"?><office:document-content '
            .'xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"/>');
        $zip->close();

        return $path;
    }

    /**
     * Sniffs as a PDF but carries no extractable text — what a scanned, image-only manuscript
     * looks like to a text parser. A common real state for submissions in the region, and the one
     * the "unreadable" path exists to handle gracefully.
     */
    public static function unreadablePdf(string $path): string
    {
        file_put_contents($path, "%PDF-1.4\n".str_repeat("\x00\xFF", 200)."\n%%EOF");

        return $path;
    }

    /** A minimal but valid single-page PDF with a text stream, enough for a parser to read. */
    public static function pdf(string $path, string $line): string
    {
        $content = "BT /F1 12 Tf 72 720 Td (".str_replace(['(', ')'], ['\(', '\)'], $line).") Tj ET";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R "
                ."/Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n",
            "4 0 obj\n<< /Length ".strlen($content)." >>\nstream\n{$content}\nendstream\nendobj\n",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefAt = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefAt}\n%%EOF";

        file_put_contents($path, $pdf);

        return $path;
    }
}
