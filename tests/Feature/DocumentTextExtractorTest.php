<?php

namespace Tests\Feature;

use Alpha\Journal\Ai\DocumentTextExtractor;
use RuntimeException;
use Tests\Fixtures\ManuscriptFixtures;
use Tests\TestCase;

/**
 * Manuscript -> text, the step before any model sees anything. Worth testing on its own because
 * a silent failure here doesn't look like a failure: the model dutifully extracts metadata from
 * an empty string and returns a confident-looking record of nulls.
 */
class DocumentTextExtractorTest extends TestCase
{
    private DocumentTextExtractor $extractor;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = new DocumentTextExtractor;
        $this->dir = sys_get_temp_dir().'/alpha-manuscripts-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    public function test_it_reads_the_text_of_a_docx(): void
    {
        $path = ManuscriptFixtures::docx($this->dir.'/paper.docx', [
            'Coral Bleaching in the Gulf of Thailand',
            'Sophea Chan, Dara Sok',
            'Abstract',
            'We surveyed reef sites across three seasons.',
        ]);

        $text = $this->extractor->extract($path, 'paper.docx');

        $this->assertStringContainsString('Coral Bleaching in the Gulf of Thailand', $text);
        $this->assertStringContainsString('We surveyed reef sites across three seasons.', $text);
    }

    public function test_docx_paragraphs_stay_on_separate_lines(): void
    {
        // The structural cue that separates a title from a heading from body text. If paragraph
        // tags are stripped before being turned into newlines the whole manuscript collapses onto
        // one line, and the model loses its only signal for where the title ends.
        $path = ManuscriptFixtures::docx($this->dir.'/paper.docx', ['First line', 'Second line']);

        $text = $this->extractor->extract($path, 'paper.docx');

        $this->assertStringNotContainsString('First lineSecond line', $text);
        $this->assertMatchesRegularExpression('/First line\s*\n\s*Second line/', $text);
    }

    public function test_it_decodes_xml_entities_rather_than_leaking_them(): void
    {
        $path = ManuscriptFixtures::docx($this->dir.'/paper.docx', ['Reefs & Rivers: a "case" study']);

        $text = $this->extractor->extract($path, 'paper.docx');

        $this->assertStringContainsString('Reefs & Rivers', $text);
        $this->assertStringNotContainsString('&amp;', $text);
    }

    public function test_it_reads_the_text_of_a_pdf(): void
    {
        $path = ManuscriptFixtures::pdf($this->dir.'/paper.pdf', 'Mekong Sediment Flux 1990-2020');

        $text = $this->extractor->extract($path, 'paper.pdf');

        $this->assertStringContainsString('Mekong Sediment Flux', $text);
    }

    public function test_an_unreadable_pdf_fails_with_an_explanation_a_human_can_act_on(): void
    {
        file_put_contents($path = $this->dir.'/broken.pdf', 'this is not a pdf at all');

        $this->expectException(RuntimeException::class);
        // Scanned, image-only PDFs are common for manuscripts from the region. The author needs
        // to know why nothing was filled in, not see a parser stack trace.
        $this->expectExceptionMessageMatches('/scan|could not read/i');

        $this->extractor->extract($path, 'broken.pdf');
    }

    public function test_legacy_formats_are_reported_as_unextractable_not_crashed_on(): void
    {
        // .doc and .odt remain valid SUBMISSIONS — they just can't be pre-filled from.
        $this->assertFalse($this->extractor->canExtract('paper.doc'));
        $this->assertFalse($this->extractor->canExtract('paper.odt'));
        $this->assertTrue($this->extractor->canExtract('paper.docx'));
        $this->assertTrue($this->extractor->canExtract('PAPER.PDF'));
    }
}
