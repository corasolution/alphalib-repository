<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Events\SubmissionStageChanged;
use Alpha\Journal\Listeners\PushWorksToOrcid;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Orcid\OrcidClient;
use Alpha\Journal\Workflow\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrcidTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_url_contains_the_client_id_state_and_redirect_uri(): void
    {
        config(['services.orcid.client_id' => 'test-client-id']);

        $url = app(OrcidClient::class)->authorizeUrl('https://app.test/orcid/callback', 'abc123');

        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('state=abc123', $url);
        $this->assertStringContainsString(urlencode('https://app.test/orcid/callback'), $url);
    }

    public function test_redirect_stores_state_in_session_and_redirects_to_orcid(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/orcid/redirect');

        $response->assertRedirect();
        $this->assertStringContainsString('orcid.org/oauth/authorize', $response->headers->get('Location'));
        $this->assertNotNull(session('orcid_oauth_state'));
    }

    public function test_a_valid_callback_stores_the_orcid_and_marks_authenticated(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-access-token',
                'orcid' => '0000-0002-1825-0097',
                'expires_in' => 631138518,
            ], 200),
        ]);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/orcid/redirect');
        $state = session('orcid_oauth_state');

        $response = $this->get("/orcid/callback?code=abc&state={$state}");

        $response->assertRedirect(route('dashboard'));
        $fresh = $user->fresh();
        $this->assertSame('0000-0002-1825-0097', $fresh->orcid);
        $this->assertTrue($fresh->hasAuthenticatedOrcid());
    }

    public function test_a_mismatched_state_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/orcid/redirect');

        $response = $this->get('/orcid/callback?code=abc&state=wrong-state');

        $response->assertForbidden();
        $this->assertFalse($user->fresh()->hasAuthenticatedOrcid());
    }

    public function test_a_missing_code_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/orcid/redirect');
        $state = session('orcid_oauth_state');

        $response = $this->get("/orcid/callback?state={$state}");

        $response->assertStatus(422);
    }

    public function test_a_failed_token_exchange_does_not_authenticate_the_account(): void
    {
        Http::fake(['*/oauth/token' => Http::response('invalid_grant', 400)]);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/orcid/redirect');
        $state = session('orcid_oauth_state');

        $response = $this->get("/orcid/callback?code=bad-code&state={$state}");

        $response->assertStatus(422);
        $this->assertFalse($user->fresh()->hasAuthenticatedOrcid());
    }

    public function test_push_work_sends_the_publication_title_and_doi_with_the_users_token(): void
    {
        Http::fake(['*/works' => Http::response(['ok' => true], 201)]);
        [$submission, $publication] = $this->publishedArticle();
        $author = $submission->submittedBy;
        $author->update([
            'orcid' => '0000-0002-1825-0097',
            'orcid_access_token' => 'secret-token',
            'orcid_authenticated_at' => now(),
        ]);

        $result = app(OrcidClient::class)->pushWork($author, $publication);

        $this->assertTrue($result);
        Http::assertSent(function ($request) use ($publication) {
            return str_contains($request->url(), '0000-0002-1825-0097/works')
                && $request->hasHeader('Authorization', 'Bearer secret-token')
                && $request['bulk'][0]['work']['title']['title']['value'] === $publication->title->get('en');
        });
    }

    public function test_the_listener_pushes_only_for_contributors_with_a_matching_authenticated_user(): void
    {
        Http::fake(['*/works' => Http::response(['ok' => true], 201)]);
        [$submission, $publication] = $this->publishedArticle();

        $connected = User::factory()->create([
            'orcid' => '0000-0002-1825-0097',
            'orcid_access_token' => 'secret-token',
            'orcid_authenticated_at' => now(),
        ]);
        $publication->contributors()->create([
            'given_name' => 'Connected', 'family_name' => 'Author', 'orcid' => '0000-0002-1825-0097', 'sequence' => 1,
        ]);
        // Typed-in ORCID with no linked, authenticated account — must be silently skipped.
        $publication->contributors()->create([
            'given_name' => 'Unlinked', 'family_name' => 'CoAuthor', 'orcid' => '0000-0001-5109-3700', 'sequence' => 2,
        ]);

        $publishDecision = $submission->editorialDecisions()->latest('id')->first();
        $event = new SubmissionStageChanged($submission, EditorialDecisionType::Publish, $publishDecision, $connected);

        app(PushWorksToOrcid::class)->handle($event);

        Http::assertSentCount(1);
    }

    public function test_the_listener_does_nothing_for_non_publish_decisions(): void
    {
        Http::fake();
        [$submission] = $this->publishedArticle();
        $actor = User::factory()->create();
        $decision = $submission->editorialDecisions()->create([
            'actor_id' => $actor->id, 'role_used' => 'editor', 'decision' => 'send_to_review', 'target_stage' => 'review',
        ]);

        app(PushWorksToOrcid::class)->handle(new SubmissionStageChanged(
            $submission, EditorialDecisionType::SendToReview, $decision, $actor
        ));

        Http::assertNothingSent();
    }

    /** @return array{0: Submission, 1: Publication} */
    private function publishedArticle(): array
    {
        $site = Site::create(['title' => 'Test Site']);
        $journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
        $editor = User::factory()->create();
        $journal->grantRole($editor, JournalRole::Editor);
        $author = User::factory()->create();
        $submission = Submission::create([
            'journal_id' => $journal->id,
            'submitted_by' => $author->id,
            'stage' => SubmissionStage::Production->value,
        ]);
        StageAssignment::create([
            'submission_id' => $submission->id,
            'user_id' => $editor->id,
            'stage' => SubmissionStage::Production->value,
            'role' => JournalRole::Editor->value,
        ]);
        $issue = Issue::create(['journal_id' => $journal->id, 'year' => 2026]);
        $submission->update(['issue_id' => $issue->id]);
        $publication = $submission->publications()->create([
            'title' => ['en' => 'A Published Article'],
            'status' => 'published',
            'published_at' => now(),
        ]);
        $submission->update(['current_publication_id' => $publication->id]);

        app(SubmissionWorkflow::class)->recordDecision($submission, $editor, EditorialDecisionType::Publish);

        return [$submission->fresh(), $publication->fresh()];
    }
}
