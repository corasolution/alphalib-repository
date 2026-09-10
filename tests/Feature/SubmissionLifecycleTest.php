<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\ReviewRecommendation;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\ReviewAssignment;
use Alpha\Journal\Models\Section;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Fixtures\ManuscriptFixtures;
use Tests\TestCase;

/**
 * The whole editorial path, walked once through the real HTTP endpoints:
 * submit → assign an editor → review → accept → production → issue → publish → public page.
 *
 * Every other workflow test manufactures a submission ALREADY at the stage it wants to test, with
 * an editor already assigned to exactly that stage. That makes each transition provable in
 * isolation and hides everything about moving between them — which is precisely where this
 * application was broken: no endpoint existed to assign an editor at all, and an assignment did
 * not survive a stage change. Both bugs sat behind 464 passing tests.
 *
 * So the value here is not any single assertion. It is that the path is walked end to end at
 * least once, the way a journal actually uses it.
 */
class SubmissionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $manager;

    private User $editor;

    private User $reviewer;

    private User $author;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('local');

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create([
            'site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal'], 'is_enabled' => true,
        ]);
        Section::create(['journal_id' => $this->journal->id, 'title' => ['en' => 'Articles'], 'sort_order' => 0]);

        $this->manager = User::factory()->create(['name' => 'Mia Manager']);
        $this->editor = User::factory()->create(['name' => 'Ed Editor']);
        $this->reviewer = User::factory()->create(['name' => 'Rita Reviewer']);
        $this->author = User::factory()->create(['name' => 'Ana Author']);

        $this->journal->grantRole($this->manager, JournalRole::JournalManager);
        $this->journal->grantRole($this->editor, JournalRole::Editor);
        $this->journal->grantRole($this->reviewer, JournalRole::Reviewer);
        $this->journal->grantRole($this->author, JournalRole::Author);

        $this->dir = sys_get_temp_dir().'/alpha-lifecycle-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function route(string $name, array $params = []): string
    {
        return route($name, array_merge(['journal' => $this->journal->path], $params));
    }

    private function decide(User $actor, Submission $submission, string $decision): TestResponse
    {
        return $this->actingAs($actor)->post(
            $this->route('journal.submissions.decisions.store', ['submission' => $submission->id]),
            ['decision' => $decision, 'reason' => "Recording {$decision}."],
        );
    }

    public function test_a_manuscript_travels_from_submission_to_the_public_journal_page(): void
    {
        // ---------- 1. The author submits ----------
        $manuscript = ManuscriptFixtures::docx($this->dir.'/paper.docx', [
            'Coral Bleaching in the Gulf of Thailand',
            'Abstract',
            'We surveyed 42 reef sites across three seasons.',
        ]);

        $this->actingAs($this->author)->post($this->route('journal.submissions.store'), [
            'title' => 'Coral Bleaching in the Gulf of Thailand',
            'abstract' => 'We surveyed 42 reef sites across three seasons.',
            'section_id' => Section::sole()->id,
            'manuscript' => new UploadedFile($manuscript, 'paper.docx', null, null, true),
        ])->assertSessionHasNoErrors();

        $submission = Submission::sole();
        $this->assertSame(SubmissionStage::Submission, $submission->stage);
        $this->assertNotNull($submission->current_publication_id, 'A submission must carry a publication from the start.');

        // ---------- 2. An editor is assigned ----------
        // Bug 1 was here: no endpoint existed to do this, so every submission was stuck at the
        // first decision with "has no assigned editor on stage [submission]".
        $this->actingAs($this->manager)->post(
            $this->route('journal.submissions.editors.store', ['submission' => $submission->id]),
            ['user_id' => $this->editor->id],
        )->assertSessionHasNoErrors();

        $this->assertTrue(
            $submission->stageAssignments()->where('user_id', $this->editor->id)
                ->where('stage', SubmissionStage::Submission->value)->exists(),
        );

        // ---------- 3. Sent to review ----------
        $this->decide($this->editor, $submission, 'send_to_review')->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStage::Review, $submission->fresh()->stage);

        // Bug 2 was here: the stage moved but the assignment did not follow, so the editor lost
        // authority over the submission by acting on it.
        $this->assertTrue(
            $submission->stageAssignments()->where('user_id', $this->editor->id)
                ->where('stage', SubmissionStage::Review->value)->exists(),
            'The assigned editor must carry through to the new stage.',
        );

        // ---------- 4. A reviewer is invited and reports back ----------
        $this->actingAs($this->editor)->post(
            $this->route('journal.submissions.review-assignments.store', ['submission' => $submission->id]),
            ['reviewer_id' => $this->reviewer->id],
        )->assertSessionHasNoErrors();

        $assignment = ReviewAssignment::sole();
        $assignment->update(['responded_at' => now(), 'declined_at' => null]);

        $this->actingAs($this->reviewer)->post(
            $this->route('journal.reviews.store', ['reviewAssignment' => $assignment->id]),
            ['recommendation' => ReviewRecommendation::Accept->value, 'answers' => []],
        )->assertSessionHasNoErrors();

        $this->assertNotNull($assignment->fresh()->completed_at);

        // ---------- 5. Accepted, then sent to production ----------
        $this->decide($this->editor, $submission, 'accept')->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStage::Copyediting, $submission->fresh()->stage);

        $this->decide($this->editor, $submission, 'send_to_production')->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStage::Production, $submission->fresh()->stage);

        // ---------- 5b. The readable file is produced ----------
        $galleyPath = ManuscriptFixtures::pdf($this->dir.'/paper.pdf', 'Coral Bleaching in the Gulf of Thailand');
        $this->actingAs($this->editor)->post(
            $this->route('journal.submissions.galleys.store', ['submission' => $submission->id]),
            ['label' => 'PDF', 'file' => new UploadedFile($galleyPath, 'paper.pdf', null, null, true)],
        )->assertSessionHasNoErrors();

        // ---------- 6. Assigned to an issue ----------
        $issue = Issue::create([
            'journal_id' => $this->journal->id, 'volume' => 1, 'number' => 1, 'year' => 2026,
            'is_published' => true, 'publish_at' => now(),
        ]);

        $this->actingAs($this->editor)->post(
            $this->route('journal.submissions.issue-assignment.store', ['submission' => $submission->id]),
            ['issue_id' => $issue->id],
        )->assertSessionHasNoErrors();

        // ---------- 7. Published ----------
        $this->decide($this->manager, $submission, 'publish')->assertSessionHasNoErrors();

        $submission->refresh();
        $this->assertSame(SubmissionStage::Published, $submission->stage);
        $this->assertSame('published', $submission->currentPublication->status);
        $this->assertNotNull($submission->currentPublication->published_at);

        // ---------- 8. A reader can see it ----------
        // The double gate in Publication::scopePublishedIn() means this only appears when the
        // publication row AND the submission stage both say published.
        $this->get('/journal/demo')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('currentIssue.articles', 1)
            ->where('currentIssue.articles.0.title', 'Coral Bleaching in the Gulf of Thailand')
        );

        // ---------- 9. ...and can actually open and read it ----------
        // The journey used to stop at the line above: a title in a list. Publishing that a reader
        // cannot open is not publishing, so the walk now ends where a real one does — on the
        // article page, with the file in hand.
        $publication = $submission->currentPublication;

        $this->get("/journal/demo/article/{$publication->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public/article')
                ->where('article.title', 'Coral Bleaching in the Gulf of Thailand')
                ->has('article.galleys', 1));

        $galleyId = $publication->galleys()->sole()->id;
        $download = $this->get("/journal/demo/article/{$publication->id}/galley/{$galleyId}");
        $this->assertContains($download->getStatusCode(), [200, 302], 'A published galley must be reachable.');

        $this->get("/journal/demo/article/{$publication->id}/cite/bibtex")
            ->assertOk()
            ->assertSee('Coral Bleaching in the Gulf of Thailand');
    }

    /**
     * An unstaffed journal has to say so.
     *
     * The submission screen showed "everyone with an editor role is already assigned" whenever the
     * picker was empty — including on a journal where nobody holds the role at all. That reading is
     * false, and it hides the only thing standing between the submission and a decision.
     */
    /**
     * The manager runs the journal, so they can open its submissions.
     *
     * They were already permitted to assign editors and to publish — but both are done from the
     * submission page, which view() refused them. Every existing test posted straight to the
     * decision endpoints, so the whole suite stayed green while the person who runs a
     * manager-only journal could not open a single submission on it.
     */
    public function test_a_journal_manager_can_open_a_submission_on_their_journal(): void
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id, 'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value, 'submitted_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->get($this->route('journal.submissions.show', ['submission' => $submission->id]))
            ->assertOk();
    }

    public function test_someone_with_no_role_still_cannot_open_a_submission(): void
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id, 'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value, 'submitted_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->get($this->route('journal.submissions.show', ['submission' => $submission->id]))
            ->assertForbidden();

        // A reviewer reaches their assigned review through its own route, never through this one.
        $this->actingAs($this->reviewer)
            ->get($this->route('journal.submissions.show', ['submission' => $submission->id]))
            ->assertForbidden();
    }

    public function test_the_screen_distinguishes_no_editors_from_all_editors_assigned(): void
    {
        // A journal staffed only by a manager. journal_manager is deliberately not an assignable
        // editorial role, so this journal genuinely has nobody to assign.
        $bare = Journal::create([
            'site_id' => $this->journal->site_id, 'path' => 'bare',
            'title' => ['en' => 'Bare Journal'], 'is_enabled' => true,
        ]);
        $bare->grantRole($this->manager, JournalRole::JournalManager);
        $bare->grantRole($this->author, JournalRole::Author);
        $this->manager->unsetRelations();

        $orphan = Submission::create([
            'journal_id' => $bare->id, 'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value, 'submitted_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->get(route('journal.submissions.show', ['journal' => 'bare', 'submission' => $orphan->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('journalHasEditors', false)
                ->has('assignableEditors', 0));

        // The staffed journal from setUp, where an Editor does exist.
        $staffed = Submission::create([
            'journal_id' => $this->journal->id, 'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value, 'submitted_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->get($this->route('journal.submissions.show', ['submission' => $staffed->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('journalHasEditors', true)
                ->has('assignableEditors', 1));
    }

    public function test_a_submission_with_no_editor_cannot_be_moved(): void
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id, 'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value, 'submitted_at' => now(),
        ]);

        // The guard that made bug 1 fatal. It is correct and stays — the fix was giving people a
        // way to satisfy it, not removing it.
        $this->decide($this->editor, $submission, 'send_to_review')->assertSessionHasErrors('decision');
        $this->assertSame(SubmissionStage::Submission, $submission->fresh()->stage);
    }

    public function test_only_editorial_roles_carry_forward_to_the_next_stage(): void
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id, 'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value, 'submitted_at' => now(),
        ]);
        StageAssignment::create([
            'submission_id' => $submission->id, 'user_id' => $this->editor->id,
            'stage' => SubmissionStage::Submission->value, 'role' => JournalRole::Editor->value,
        ]);

        $copyeditor = User::factory()->create();
        $this->journal->grantRole($copyeditor, JournalRole::Copyeditor);
        StageAssignment::create([
            'submission_id' => $submission->id, 'user_id' => $copyeditor->id,
            'stage' => SubmissionStage::Submission->value, 'role' => JournalRole::Copyeditor->value,
        ]);

        $this->decide($this->editor, $submission, 'send_to_review')->assertSessionHasNoErrors();

        // A copyeditor has no business being auto-attached to the Review stage, and the editor
        // guard does not consider them anyway.
        $carried = $submission->stageAssignments()->where('stage', SubmissionStage::Review->value)->pluck('user_id');
        $this->assertContains($this->editor->id, $carried->all());
        $this->assertNotContains($copyeditor->id, $carried->all());
    }

    public function test_an_editor_can_be_assigned_and_removed(): void
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id, 'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value, 'submitted_at' => now(),
        ]);

        $this->actingAs($this->manager)->post(
            $this->route('journal.submissions.editors.store', ['submission' => $submission->id]),
            ['user_id' => $this->editor->id],
        )->assertSessionHasNoErrors();

        $assignment = StageAssignment::sole();

        $this->actingAs($this->manager)->delete($this->route('journal.submissions.editors.destroy', [
            'submission' => $submission->id, 'stageAssignment' => $assignment->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(0, StageAssignment::count());
    }

    public function test_assigning_the_same_editor_twice_does_not_duplicate(): void
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id, 'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value, 'submitted_at' => now(),
        ]);

        foreach (range(1, 2) as $ignored) {
            $this->actingAs($this->manager)->post(
                $this->route('journal.submissions.editors.store', ['submission' => $submission->id]),
                ['user_id' => $this->editor->id],
            );
        }

        $this->assertSame(1, StageAssignment::count());
    }

    public function test_someone_without_an_editorial_role_cannot_be_assigned_as_editor(): void
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id, 'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value, 'submitted_at' => now(),
        ]);

        // Assigning the author would hand them decision authority over their own manuscript.
        $this->actingAs($this->manager)->post(
            $this->route('journal.submissions.editors.store', ['submission' => $submission->id]),
            ['user_id' => $this->author->id],
        )->assertSessionHasErrors('user_id');

        $this->assertSame(0, StageAssignment::count());
    }

    public function test_a_reviewer_cannot_assign_editors(): void
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id, 'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value, 'submitted_at' => now(),
        ]);

        $this->actingAs($this->reviewer)->post(
            $this->route('journal.submissions.editors.store', ['submission' => $submission->id]),
            ['user_id' => $this->editor->id],
        )->assertForbidden();

        $this->assertSame(0, StageAssignment::count());
    }

    public function test_a_journal_manager_can_publish_but_not_make_other_decisions(): void
    {
        // The third bug: Gate::authorize('recordDecision') runs for every decision including
        // publish, and excluded Journal Managers — so the person who runs the journal was 403'd
        // out of the one decision §4.1 specifically empowers them to make.
        $submission = Submission::create([
            'journal_id' => $this->journal->id, 'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Production->value, 'submitted_at' => now(),
        ]);
        StageAssignment::create([
            'submission_id' => $submission->id, 'user_id' => $this->editor->id,
            'stage' => SubmissionStage::Production->value, 'role' => JournalRole::Editor->value,
        ]);
        $publication = $submission->publications()->create([
            'version' => 1, 'title' => ['en' => 'A Paper'], 'status' => 'draft',
        ]);
        $submission->update([
            'current_publication_id' => $publication->id,
            'issue_id' => Issue::create([
                'journal_id' => $this->journal->id, 'volume' => 1, 'number' => 1, 'year' => 2026,
                'is_published' => true, 'publish_at' => now(),
            ])->id,
        ]);

        $this->decide($this->manager, $submission, 'publish')->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStage::Published, $submission->fresh()->stage);
    }

    public function test_a_journal_manager_is_still_refused_a_decision_they_have_no_authority_for(): void
    {
        // Widening the coarse gate must not widen actual authority: approvalRole() still refuses
        // a Journal Manager every decision other than publish.
        $submission = Submission::create([
            'journal_id' => $this->journal->id, 'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Submission->value, 'submitted_at' => now(),
        ]);
        StageAssignment::create([
            'submission_id' => $submission->id, 'user_id' => $this->editor->id,
            'stage' => SubmissionStage::Submission->value, 'role' => JournalRole::Editor->value,
        ]);

        $this->decide($this->manager, $submission, 'send_to_review')->assertSessionHasErrors('decision');
        $this->assertSame(SubmissionStage::Submission, $submission->fresh()->stage);
    }

    public function test_a_declined_submission_never_reaches_the_public_page(): void
    {
        $submission = Submission::create([
            'journal_id' => $this->journal->id, 'submitted_by' => $this->author->id,
            'stage' => SubmissionStage::Review->value, 'submitted_at' => now(),
        ]);
        StageAssignment::create([
            'submission_id' => $submission->id, 'user_id' => $this->editor->id,
            'stage' => SubmissionStage::Review->value, 'role' => JournalRole::Editor->value,
        ]);

        $this->decide($this->editor, $submission, 'decline')->assertSessionHasNoErrors();

        $this->assertSame(SubmissionStage::Declined, $submission->fresh()->stage);
        // Terminal stages get no carried assignments — nothing would ever read them.
        $this->assertSame(0, $submission->stageAssignments()->where('stage', SubmissionStage::Declined->value)->count());
    }
}
