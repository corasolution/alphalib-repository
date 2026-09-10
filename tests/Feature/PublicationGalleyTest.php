<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\ActivityLog;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\PublicationGalley;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\ManuscriptFixtures;
use Tests\TestCase;

/**
 * Producing the readable file — the Production-stage step between "accepted" and "a reader can
 * download this".
 *
 * Galleys hang off a publication rather than a submission on purpose: a corrected version starts
 * without its predecessor's files, so a v2 can never silently serve the v1 PDF.
 */
class PublicationGalleyTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private Submission $submission;

    private Publication $publication;

    private User $editor;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create([
            'site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal'], 'is_enabled' => true,
        ]);

        $this->editor = User::factory()->create();
        $this->journal->grantRole($this->editor, JournalRole::Editor);

        $author = User::factory()->create();
        $this->journal->grantRole($author, JournalRole::Author);

        $this->submission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $author->id,
            'stage' => SubmissionStage::Production->value,
            'submitted_at' => now(),
        ]);

        $this->publication = Publication::create([
            'submission_id' => $this->submission->id,
            'version' => 1,
            'title' => ['en' => 'A Production-Ready Paper'],
            'status' => 'draft',
        ]);

        $this->submission->update(['current_publication_id' => $this->publication->id]);

        $this->dir = sys_get_temp_dir().'/alpha-galley-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function pdf(string $name = 'galley.pdf'): UploadedFile
    {
        $path = ManuscriptFixtures::pdf($this->dir.'/'.$name, 'A Production-Ready Paper');

        return new UploadedFile($path, $name, null, null, true);
    }

    private function storeRoute(): string
    {
        return route('journal.submissions.galleys.store', [
            'journal' => $this->journal->path, 'submission' => $this->submission->id,
        ]);
    }

    public function test_an_editor_can_attach_a_galley(): void
    {
        $this->actingAs($this->editor)
            ->post($this->storeRoute(), ['label' => 'PDF', 'file' => $this->pdf()])
            ->assertSessionHasNoErrors();

        $galley = PublicationGalley::sole();

        $this->assertSame('PDF', $galley->label);
        $this->assertSame($this->publication->id, $galley->publication_id);
        $this->assertNotNull($galley->sha256, 'A stored file must carry its checksum.');
        Storage::disk('r2')->assertExists($galley->path);
    }

    public function test_attaching_a_galley_is_recorded_on_the_timeline(): void
    {
        // The submission history is the audit trail an editor relies on; a file appearing with no
        // record of who put it there is exactly what it exists to prevent.
        $this->actingAs($this->editor)->post($this->storeRoute(), ['label' => 'PDF', 'file' => $this->pdf()]);

        $this->assertTrue(
            ActivityLog::where('action', 'submission.galley_added')
                ->where('subject_type', Submission::class)
                ->where('subject_id', $this->submission->id)
                ->exists(),
        );
    }

    public function test_galleys_are_ordered_as_they_are_added(): void
    {
        $this->actingAs($this->editor)->post($this->storeRoute(), ['label' => 'PDF', 'file' => $this->pdf('a.pdf')]);
        $this->actingAs($this->editor)->post($this->storeRoute(), ['label' => 'XML', 'file' => $this->pdf('b.pdf')]);

        $order = PublicationGalley::orderBy('sort_order')->pluck('label')->all();

        $this->assertSame(['PDF', 'XML'], $order);
    }

    public function test_an_editor_can_remove_a_galley(): void
    {
        $this->actingAs($this->editor)->post($this->storeRoute(), ['label' => 'PDF', 'file' => $this->pdf()]);
        $galley = PublicationGalley::sole();

        $this->actingAs($this->editor)->delete(route('journal.submissions.galleys.destroy', [
            'journal' => $this->journal->path,
            'submission' => $this->submission->id,
            'publicationGalley' => $galley->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(0, PublicationGalley::count());
    }

    public function test_a_reviewer_cannot_attach_a_galley(): void
    {
        $reviewer = User::factory()->create();
        $this->journal->grantRole($reviewer, JournalRole::Reviewer);

        $this->actingAs($reviewer)
            ->post($this->storeRoute(), ['label' => 'PDF', 'file' => $this->pdf()])
            ->assertForbidden();

        $this->assertSame(0, PublicationGalley::count());
    }

    public function test_a_stranger_cannot_attach_a_galley(): void
    {
        $this->actingAs(User::factory()->create())
            ->post($this->storeRoute(), ['label' => 'PDF', 'file' => $this->pdf()])
            ->assertForbidden();
    }

    public function test_a_galley_from_another_submission_cannot_be_removed_through_this_one(): void
    {
        // Scoped through the submission: an id belonging to another article must not resolve,
        // or one editor could delete another's files by guessing a number.
        $otherSubmission = Submission::create([
            'journal_id' => $this->journal->id,
            'submitted_by' => $this->editor->id,
            'stage' => SubmissionStage::Production->value,
            'submitted_at' => now(),
        ]);
        $otherPublication = Publication::create([
            'submission_id' => $otherSubmission->id, 'version' => 1,
            'title' => ['en' => 'Someone Else'], 'status' => 'draft',
        ]);
        $foreign = PublicationGalley::storeFor($otherPublication, $this->pdf('other.pdf'), 'PDF');

        $this->actingAs($this->editor)->delete(route('journal.submissions.galleys.destroy', [
            'journal' => $this->journal->path,
            'submission' => $this->submission->id,
            'publicationGalley' => $foreign->id,
        ]))->assertNotFound();

        $this->assertSame(1, PublicationGalley::count());
    }

    public function test_a_format_no_reader_could_open_is_refused(): void
    {
        $path = $this->dir.'/notes.txt';
        file_put_contents($path, 'not a galley');

        $this->actingAs($this->editor)
            ->post($this->storeRoute(), ['label' => 'PDF', 'file' => new UploadedFile($path, 'notes.txt', null, null, true)])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, PublicationGalley::count());
    }

    public function test_a_label_is_required(): void
    {
        $this->actingAs($this->editor)
            ->post($this->storeRoute(), ['file' => $this->pdf()])
            ->assertSessionHasErrors('label');
    }
}
