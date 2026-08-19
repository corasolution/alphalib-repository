<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Section;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Workflow\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OaiPmhTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $editor;

    /**
     * Drives a submission all the way to Published so it's actually eligible for OAI exposure —
     * this is the only path that legitimately produces a Publication with status='published'.
     */
    private function publishArticle(array $titleByLocale = ['en' => 'A Published Article'], ?Section $section = null): array
    {
        $author = User::factory()->create();
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'section_id' => $section?->id,
            'submitted_by' => $author->id,
            'stage' => SubmissionStage::Production->value,
        ]);
        StageAssignment::create([
            'submission_id' => $submission->id,
            'user_id' => $this->editor->id,
            'stage' => SubmissionStage::Production->value,
            'role' => JournalRole::Editor->value,
        ]);
        $issue = Issue::create(['journal_id' => $this->journal->id, 'year' => 2026]);
        $submission->update(['issue_id' => $issue->id]);
        $publication = $submission->publications()->create(['title' => $titleByLocale]);
        $submission->update(['current_publication_id' => $publication->id]);
        $publication->contributors()->create(['given_name' => 'Ada', 'family_name' => 'Lovelace', 'sequence' => 1]);

        app(SubmissionWorkflow::class)->recordDecision($submission, $this->editor, EditorialDecisionType::Publish);

        return [$submission->fresh(), $publication->fresh()];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
        $this->editor = User::factory()->create();
        $this->journal->grantRole($this->editor, JournalRole::Editor);
    }

    private function oai(array $query): TestResponse
    {
        return $this->get('/oai?'.http_build_query($query));
    }

    public function test_a_missing_verb_returns_bad_verb(): void
    {
        $response = $this->oai([]);

        $response->assertOk();
        $response->assertSee('badVerb');
    }

    public function test_an_unknown_verb_returns_bad_verb(): void
    {
        $response = $this->oai(['verb' => 'NotAVerb']);

        $response->assertSee('code="badVerb"', false);
    }

    public function test_identify_reports_persistent_deleted_record_policy(): void
    {
        $response = $this->oai(['verb' => 'Identify']);

        $response->assertOk();
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'response is not well-formed XML');
        $this->assertSame('2.0', (string) $xml->Identify->protocolVersion);
        $this->assertSame('persistent', (string) $xml->Identify->deletedRecord);
    }

    public function test_list_metadata_formats_advertises_only_oai_dc(): void
    {
        $response = $this->oai(['verb' => 'ListMetadataFormats']);

        $xml = simplexml_load_string($response->getContent());
        $this->assertCount(1, $xml->ListMetadataFormats->metadataFormat);
        $this->assertSame('oai_dc', (string) $xml->ListMetadataFormats->metadataFormat->metadataPrefix);
    }

    public function test_list_sets_includes_the_journal_and_its_sections(): void
    {
        Section::create(['journal_id' => $this->journal->id, 'title' => ['en' => 'Articles']]);

        $response = $this->oai(['verb' => 'ListSets']);

        $xml = simplexml_load_string($response->getContent());
        $xml->registerXPathNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
        $specs = array_map('strval', $xml->xpath('//oai:setSpec'));
        $this->assertContains('journal:demo', $specs);
        $this->assertTrue(collect($specs)->contains(fn ($s) => str_starts_with($s, 'journal:demo:')));
    }

    public function test_list_sets_with_no_enabled_journals_returns_no_set_hierarchy(): void
    {
        $this->journal->update(['is_enabled' => false]);

        $response = $this->oai(['verb' => 'ListSets']);

        $response->assertSee('code="noSetHierarchy"', false);
    }

    public function test_get_record_returns_dublin_core_metadata_for_a_published_article(): void
    {
        [, $publication] = $this->publishArticle(['en' => 'Rice Yield Under Drought']);
        $identifier = "oai:localhost:demo/{$publication->id}";

        $response = $this->oai(['verb' => 'GetRecord', 'identifier' => $identifier, 'metadataPrefix' => 'oai_dc']);

        $xml = simplexml_load_string($response->getContent());
        $dc = $xml->GetRecord->record->metadata->children('http://www.openarchives.org/OAI/2.0/oai_dc/')->dc
            ->children('http://purl.org/dc/elements/1.1/');
        $this->assertSame('Rice Yield Under Drought', (string) $dc->title);
        $this->assertSame('Lovelace, Ada', (string) $dc->creator);
    }

    public function test_get_record_for_an_unpublished_submission_does_not_exist(): void
    {
        $author = User::factory()->create();
        $submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $author->id,
            'stage' => SubmissionStage::Submission->value,
        ]);
        $publication = $submission->publications()->create(['title' => ['en' => 'Draft']]);
        $identifier = "oai:localhost:demo/{$publication->id}";

        $response = $this->oai(['verb' => 'GetRecord', 'identifier' => $identifier, 'metadataPrefix' => 'oai_dc']);

        $response->assertSee('code="idDoesNotExist"', false);
    }

    public function test_get_record_with_an_unsupported_format_cannot_disseminate(): void
    {
        [, $publication] = $this->publishArticle();
        $identifier = "oai:localhost:demo/{$publication->id}";

        $response = $this->oai(['verb' => 'GetRecord', 'identifier' => $identifier, 'metadataPrefix' => 'jats']);

        $response->assertSee('code="cannotDisseminateFormat"', false);
    }

    public function test_list_records_omits_articles_on_a_disabled_journal(): void
    {
        $this->publishArticle();
        $this->journal->update(['is_enabled' => false]);

        $response = $this->oai(['verb' => 'ListRecords', 'metadataPrefix' => 'oai_dc']);

        $response->assertSee('code="noRecordsMatch"', false);
    }

    public function test_list_identifiers_paginates_with_a_resumption_token(): void
    {
        config(['oai.page_size' => 2]);
        $this->publishArticle(['en' => 'Article 1']);
        $this->publishArticle(['en' => 'Article 2']);
        $this->publishArticle(['en' => 'Article 3']);

        $first = $this->oai(['verb' => 'ListIdentifiers', 'metadataPrefix' => 'oai_dc']);
        $firstXml = simplexml_load_string($first->getContent());
        $this->assertCount(2, $firstXml->ListIdentifiers->header);
        $token = (string) $firstXml->ListIdentifiers->resumptionToken;
        $this->assertNotSame('', $token);
        $this->assertSame('3', (string) $firstXml->ListIdentifiers->resumptionToken['completeListSize']);

        $second = $this->oai(['verb' => 'ListIdentifiers', 'resumptionToken' => $token]);
        $secondXml = simplexml_load_string($second->getContent());
        $this->assertCount(1, $secondXml->ListIdentifiers->header);
        $this->assertSame('', (string) $secondXml->ListIdentifiers->resumptionToken);
    }

    public function test_combining_a_resumption_token_with_other_arguments_is_a_bad_argument(): void
    {
        config(['oai.page_size' => 1]);
        $this->publishArticle();
        $this->publishArticle();
        $first = simplexml_load_string(
            $this->oai(['verb' => 'ListIdentifiers', 'metadataPrefix' => 'oai_dc'])->getContent()
        );
        $token = (string) $first->ListIdentifiers->resumptionToken;

        $response = $this->oai(['verb' => 'ListIdentifiers', 'resumptionToken' => $token, 'set' => 'journal:demo']);

        $response->assertSee('code="badArgument"', false);
    }

    public function test_an_invalid_resumption_token_is_rejected(): void
    {
        $response = $this->oai(['verb' => 'ListRecords', 'resumptionToken' => 'not-a-real-token']);

        $response->assertSee('code="badResumptionToken"', false);
    }

    public function test_set_filter_scopes_to_a_single_journal(): void
    {
        $this->publishArticle();
        $otherJournal = Journal::create(['site_id' => $this->journal->site_id, 'path' => 'other', 'title' => ['en' => 'Other']]);
        $otherEditor = User::factory()->create();
        $otherJournal->grantRole($otherEditor, JournalRole::Editor);
        $otherAuthor = User::factory()->create();
        $otherSubmission = Submission::create([
            'journal_id' => $otherJournal->id,
            'submitted_by' => $otherAuthor->id,
            'stage' => SubmissionStage::Production->value,
        ]);
        StageAssignment::create([
            'submission_id' => $otherSubmission->id,
            'user_id' => $otherEditor->id,
            'stage' => SubmissionStage::Production->value,
            'role' => JournalRole::Editor->value,
        ]);
        $otherIssue = Issue::create(['journal_id' => $otherJournal->id, 'year' => 2026]);
        $otherSubmission->update(['issue_id' => $otherIssue->id]);
        $otherPub = $otherSubmission->publications()->create(['title' => ['en' => 'Other Journal Article']]);
        $otherSubmission->update(['current_publication_id' => $otherPub->id]);
        app(SubmissionWorkflow::class)->recordDecision($otherSubmission, $otherEditor, EditorialDecisionType::Publish);

        $response = $this->oai(['verb' => 'ListRecords', 'metadataPrefix' => 'oai_dc', 'set' => 'journal:demo']);

        $xml = simplexml_load_string($response->getContent());
        $this->assertCount(1, $xml->ListRecords->record);
    }

    public function test_from_and_until_filter_by_publication_date(): void
    {
        [, $publication] = $this->publishArticle();
        $publication->update(['published_at' => '2020-01-01 00:00:00']);

        $tooLate = $this->oai(['verb' => 'ListRecords', 'metadataPrefix' => 'oai_dc', 'from' => '2025-01-01']);
        $tooLate->assertSee('code="noRecordsMatch"', false);

        $inRange = $this->oai(['verb' => 'ListRecords', 'metadataPrefix' => 'oai_dc', 'until' => '2020-12-31']);
        $xml = simplexml_load_string($inRange->getContent());
        $this->assertCount(1, $xml->ListRecords->record);
    }
}
