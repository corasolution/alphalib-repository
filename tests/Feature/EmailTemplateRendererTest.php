<?php

namespace Tests\Feature;

use Alpha\Journal\Mail\EmailTemplateRenderer;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\JournalEmailTemplate;
use Alpha\Journal\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateRendererTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private EmailTemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'a', 'title' => ['en' => 'Journal A']]);
        $this->renderer = app(EmailTemplateRenderer::class);
    }

    public function test_it_renders_the_default_template_with_variable_substitution(): void
    {
        $rendered = $this->renderer->render($this->journal, 'submission.accepted', 'en', [
            'journal_title' => 'Cambodian Journal of Agriculture',
            'author_name' => 'Dr. Sok',
            'submission_title' => 'Rice Yield Study',
        ]);

        $this->assertNotNull($rendered);
        $this->assertStringContainsString('Cambodian Journal of Agriculture', $rendered->subject);
        $this->assertStringContainsString('Dr. Sok', $rendered->body);
        $this->assertStringContainsString('Rice Yield Study', $rendered->body);
        $this->assertStringNotContainsString('{{', $rendered->body);
    }

    public function test_a_journal_override_replaces_the_default_content(): void
    {
        JournalEmailTemplate::create([
            'journal_id' => $this->journal->id,
            'key' => 'submission.accepted',
            'locale' => 'en',
            'subject' => 'Custom subject for {{author_name}}',
            'body' => 'Custom body.',
        ]);

        $rendered = $this->renderer->render($this->journal, 'submission.accepted', 'en', ['author_name' => 'Dr. Sok']);

        $this->assertSame('Custom subject for Dr. Sok', $rendered->subject);
        $this->assertSame('Custom body.', $rendered->body);
    }

    public function test_a_disabled_journal_override_suppresses_the_template(): void
    {
        JournalEmailTemplate::create([
            'journal_id' => $this->journal->id,
            'key' => 'submission.accepted',
            'locale' => 'en',
            'is_enabled' => false,
        ]);

        $rendered = $this->renderer->render($this->journal, 'submission.accepted', 'en', []);

        $this->assertNull($rendered);
    }

    public function test_it_falls_back_to_english_when_the_requested_locale_has_no_default_file(): void
    {
        $rendered = $this->renderer->render($this->journal, 'submission.accepted', 'km', [
            'journal_title' => 'J', 'author_name' => 'A', 'submission_title' => 'S',
        ]);

        $this->assertNotNull($rendered);
        $this->assertStringContainsString('accepted for publication', $rendered->body);
    }

    public function test_an_unknown_template_key_throws(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->renderer->render($this->journal, 'no.such.template', 'en', []);
    }
}
