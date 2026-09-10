<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Route middleware order for journal-scoped routes. `Submission $submission` as an implicit
 * route-model-binding parameter is resolved by SubstituteBindings, which runs as part of
 * Laravel's default middleware priority — if it runs before the `journal` middleware
 * (ResolveJournalContext) sets JournalContext, the binding query hits JournalScope's
 * deny-by-default and 404s on every single request in production, while every existing
 * HTTP feature test stays green, because JournalScope bypasses its own deny-by-default whenever
 * app()->runningInConsole() is true — which PHPUnit satisfies even for simulated HTTP requests.
 * `journal.scope_console_bypass=false` (JournalRoleAndScopeTest's own pattern) closes that gap
 * and makes this route behave the way a real browser request actually would.
 */
class JournalScopedRouteModelBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_submission_scoped_post_route_resolves_the_implicit_binding_before_substitute_bindings_runs(): void
    {
        config(['journal.scope_console_bypass' => false]);

        $site = Site::create(['title' => 'Test Site']);
        $journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
        $editor = User::factory()->create();
        $journal->grantRole($editor, JournalRole::Editor);

        $submission = Submission::create([
            'journal_id' => $journal->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => SubmissionStage::Submission->value,
        ]);
        StageAssignment::create([
            'submission_id' => $submission->id,
            'user_id' => $editor->id,
            'stage' => SubmissionStage::Submission->value,
            'role' => JournalRole::Editor->value,
        ]);

        $response = $this->actingAs($editor)->post(
            route('journal.submissions.decisions.store', ['journal' => $journal->path, 'submission' => $submission->id]),
            ['decision' => EditorialDecisionType::SendToReview->value]
        );

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
    }
}
