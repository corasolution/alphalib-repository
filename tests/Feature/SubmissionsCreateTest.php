<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Section;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Fixtures\ManuscriptFixtures;
use Tests\TestCase;

/**
 * "Single scrolling screen... under 5 minutes for a returning author" (CLAUDE.md §13.1). Proves
 * the one-screen create flow actually produces a submission with a manuscript, a publication
 * record, and an author contributor — not just a form that renders.
 */
class SubmissionsCreateTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $author;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
        $this->author = User::factory()->create(['name' => 'Jane Doe']);
        $this->journal->grantRole($this->author, JournalRole::Author);

        $this->dir = sys_get_temp_dir().'/alpha-create-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    /**
     * A real file on disk, not UploadedFile::fake().
     *
     * fake() *hands* Laravel the MIME type instead of letting it sniff the bytes, so `mimes:` is
     * never actually exercised — which is exactly how "DOCX sniffs as application/zip" stayed
     * hidden until a human tried it in a browser. These go through the same detection path a
     * browser upload does.
     */
    private function realFile(string $name): UploadedFile
    {
        $path = str_ends_with($name, '.pdf')
            ? ManuscriptFixtures::pdf($this->dir.'/'.$name, 'A Study of Something Important')
            : ManuscriptFixtures::docx($this->dir.'/'.$name, ['A Study of Something Important']);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_the_create_form_renders_with_the_journals_sections(): void
    {
        Section::create(['journal_id' => $this->journal->id, 'title' => ['en' => 'Articles']]);

        $response = $this->actingAs($this->author)->get(
            route('journal.submissions.create', ['journal' => $this->journal->path])
        );

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('journal/submissions/create')
            ->has('sections', 1)
            ->where('sections.0.title', 'Articles')
        );
    }

    public function test_a_stranger_cannot_start_a_submission(): void
    {
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->get(
            route('journal.submissions.create', ['journal' => $this->journal->path])
        );

        $response->assertForbidden();
    }

    public function test_submitting_creates_a_submission_with_a_manuscript_publication_and_contributor(): void
    {
        Storage::fake('r2');

        $response = $this->actingAs($this->author)->post(
            route('journal.submissions.store', ['journal' => $this->journal->path]),
            [
                'title' => 'A Study of Something Important',
                'abstract' => 'This paper studies something important.',
                'manuscript' => $this->realFile('manuscript.pdf'),
            ]
        );

        $submission = Submission::sole();

        $response->assertRedirect(route('journal.submissions.show', ['journal' => $this->journal->path, 'submission' => $submission->id]));

        $this->assertSame($this->author->id, $submission->submitted_by);
        $this->assertNotNull($submission->current_publication_id);
        $this->assertSame('A Study of Something Important', $submission->currentPublication->title->get('en'));

        $this->assertNotNull($submission->currentManuscript);
        $this->assertTrue($submission->currentManuscript->is_current_manuscript);
        Storage::disk('r2')->assertExists($submission->currentManuscript->path);

        $contributor = $submission->currentPublication->contributors->sole();
        $this->assertSame('Jane', $contributor->given_name);
        $this->assertSame('Doe', $contributor->family_name);
        $this->assertSame($this->author->email, $contributor->email);
    }

    public function test_a_real_docx_is_accepted(): void
    {
        // The format most authors actually submit, and the one whose MIME sniffs as a zip.
        Storage::fake('r2');

        $this->actingAs($this->author)->post(
            route('journal.submissions.store', ['journal' => $this->journal->path]),
            ['title' => 'A Study of Something Important', 'manuscript' => $this->realFile('manuscript.docx')],
        )->assertSessionHasNoErrors();

        $this->assertNotNull(Submission::sole()->currentManuscript);
    }

    public function test_a_format_the_journal_does_not_accept_is_refused(): void
    {
        Storage::fake('r2');

        $path = $this->dir.'/notes.txt';
        file_put_contents($path, 'Just some notes, not a manuscript.');

        $this->actingAs($this->author)->post(
            route('journal.submissions.store', ['journal' => $this->journal->path]),
            ['title' => 'Wrong Format', 'manuscript' => new UploadedFile($path, 'notes.txt', null, null, true)],
        )->assertSessionHasErrors('manuscript');

        $this->assertSame(0, Submission::count());
    }

    public function test_the_manuscript_is_required(): void
    {
        $response = $this->actingAs($this->author)->post(
            route('journal.submissions.store', ['journal' => $this->journal->path]),
            ['title' => 'No File Attached']
        );

        $response->assertSessionHasErrors('manuscript');
        $this->assertSame(0, Submission::count());
    }

    public function test_a_reader_with_no_role_cannot_submit(): void
    {
        $reader = User::factory()->create();

        $response = $this->actingAs($reader)->post(
            route('journal.submissions.store', ['journal' => $this->journal->path]),
            ['title' => 'Should Not Save']
        );

        $response->assertForbidden();
        $this->assertSame(0, Submission::count());
    }
}
