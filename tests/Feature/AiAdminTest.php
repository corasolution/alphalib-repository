<?php

namespace Tests\Feature;

use Alpha\Journal\Ai\AiRunner;
use Alpha\Journal\Ai\Providers\AiCredentials;
use Alpha\Journal\Ai\Providers\GeminiProvider;
use Alpha\Journal\Ai\Providers\ProviderRegistry;
use Alpha\Journal\Enums\AiRunStatus;
use Alpha\Journal\Models\AiRun;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\TestCase;

/**
 * The site-admin AI screen: provider credentials, the installation switch, and the ai_runs
 * audit trail (CLAUDE.md §6 guardrail 3, §4's Auditor role).
 *
 * The load-bearing property throughout is that a stored key is billable and must never travel
 * back to a browser — several tests exist only to prove that.
 */
class AiAdminTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create(['title' => 'Test Site']);
        $this->admin = User::factory()->create(['is_site_admin' => true]);

        config()->set('services.anthropic.key', null);
        config()->set('services.gemini.key', null);
    }

    public function test_an_admin_can_save_and_replace_a_provider_key(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.ai.key.update'), ['provider' => 'anthropic', 'key' => 'sk-ant-abcd1234'])
            ->assertSessionHasNoErrors();

        $this->assertSame('sk-ant-abcd1234', app(AiCredentials::class)->for('anthropic'));

        $this->actingAs($this->admin)
            ->put(route('admin.ai.key.update'), ['provider' => 'anthropic', 'key' => 'sk-ant-replaced9999']);

        $this->assertSame('sk-ant-replaced9999', app(AiCredentials::class)->for('anthropic'));
    }

    public function test_each_provider_key_is_stored_independently(): void
    {
        $this->actingAs($this->admin)->put(route('admin.ai.key.update'), ['provider' => 'anthropic', 'key' => 'sk-ant-1']);
        $this->actingAs($this->admin)->put(route('admin.ai.key.update'), ['provider' => 'gemini', 'key' => 'AIza-2']);

        // Saving one must not clear the other — they share a single encrypted column.
        $this->assertSame('sk-ant-1', app(AiCredentials::class)->for('anthropic'));
        $this->assertSame('AIza-2', app(AiCredentials::class)->for('gemini'));
    }

    public function test_the_key_is_encrypted_at_rest(): void
    {
        $this->actingAs($this->admin)->put(route('admin.ai.key.update'), ['provider' => 'anthropic', 'key' => 'sk-ant-secret-value']);

        $raw = DB::table('sites')->value('ai_credentials');

        // A database dump, a replica, or a backup must not hand anyone a working billable key.
        $this->assertStringNotContainsString('sk-ant-secret-value', (string) $raw);
    }

    public function test_the_screen_never_returns_the_key_itself(): void
    {
        $this->actingAs($this->admin)->put(route('admin.ai.key.update'), ['provider' => 'anthropic', 'key' => 'sk-ant-secret-value']);

        $response = $this->actingAs($this->admin)->get(route('admin.ai.index'));

        $response->assertOk();
        $response->assertDontSee('sk-ant-secret-value');
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('providers.0.configured', true)
            // Only the last four, enough to tell which key is loaded and useless to anyone else.
            ->where('providers.0.hint', '…alue')
            ->where('providers.0.source', 'database')
        );
    }

    public function test_clearing_a_key_falls_back_to_the_environment(): void
    {
        $this->actingAs($this->admin)->put(route('admin.ai.key.update'), ['provider' => 'anthropic', 'key' => 'sk-ant-from-db']);
        config()->set('services.anthropic.key', 'sk-ant-from-env');

        $this->assertSame('sk-ant-from-db', app(AiCredentials::class)->for('anthropic'));
        $this->assertSame('database', app(AiCredentials::class)->source('anthropic'));

        $this->actingAs($this->admin)->put(route('admin.ai.key.update'), ['provider' => 'anthropic', 'key' => '']);

        // The .env fallback is what keeps an existing self-hosted install working after this
        // feature ships — nobody has to visit a screen before their AI keeps functioning.
        $this->assertSame('sk-ant-from-env', app(AiCredentials::class)->for('anthropic'));
        $this->assertSame('env', app(AiCredentials::class)->source('anthropic'));
    }

    public function test_an_unknown_provider_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.ai.key.update'), ['provider' => 'openai', 'key' => 'sk-whatever'])
            ->assertSessionHasErrors('provider');
    }

    public function test_the_installation_switch_overrides_the_config_default(): void
    {
        config()->set('ai.enabled', true);
        $runner = app(AiRunner::class);

        $this->assertTrue($runner->installationEnabled());

        $this->actingAs($this->admin)->put(route('admin.ai.switch'), ['enabled' => false])->assertSessionHasNoErrors();

        $this->assertFalse(app(AiRunner::class)->installationEnabled());
    }

    public function test_a_never_configured_switch_leaves_config_in_charge(): void
    {
        // Nullable on purpose: "never decided" must stay distinguishable from "switched off".
        $this->assertNull($this->site->fresh()->ai_enabled);

        config()->set('ai.enabled', false);
        $this->assertFalse(app(AiRunner::class)->installationEnabled());

        config()->set('ai.enabled', true);
        $this->assertTrue(app(AiRunner::class)->installationEnabled());
    }

    public function test_a_failing_connection_test_reports_rather_than_500s(): void
    {
        $this->actingAs($this->admin)->put(route('admin.ai.key.update'), ['provider' => 'anthropic', 'key' => 'sk-bad']);
        Http::fake(['api.anthropic.com/*' => Http::response('{"error":"invalid x-api-key"}', 401)]);

        $this->actingAs($this->admin)
            ->post(route('admin.ai.test'), ['provider' => 'anthropic'])
            // A bad key is an expected outcome of this button, not a server fault.
            ->assertSessionHasErrors('key');
    }

    public function test_a_successful_connection_test_reports_success(): void
    {
        $this->actingAs($this->admin)->put(route('admin.ai.key.update'), ['provider' => 'gemini', 'key' => 'AIza-good']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => []])]);

        $this->actingAs($this->admin)
            ->post(route('admin.ai.test'), ['provider' => 'gemini'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    public function test_the_run_log_and_spend_totals_reach_the_screen(): void
    {
        $journal = Journal::create(['site_id' => $this->site->id, 'path' => 'demo', 'title' => ['en' => 'Demo']]);

        AiRun::withoutGlobalScopes()->create([
            'journal_id' => $journal->id, 'feature' => 'metadata_extraction',
            'model' => 'claude-haiku-4-5-20251001', 'prompt_version' => 'v1',
            'status' => AiRunStatus::Succeeded->value,
            'input_tokens' => 4000, 'output_tokens' => 300, 'cost_microcents' => 5500, 'latency_ms' => 210,
        ]);
        AiRun::withoutGlobalScopes()->create([
            'journal_id' => $journal->id, 'feature' => 'metadata_extraction',
            'model' => 'claude-haiku-4-5-20251001', 'prompt_version' => 'v1',
            'status' => AiRunStatus::Blocked->value,
        ]);

        $this->actingAs($this->admin)->get(route('admin.ai.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('runs', 2)
            ->where('spend.totalRuns', 2)
            ->where('spend.totalCents', 0.55)
            ->where('spend.totalTokens', 4300)
            ->has('spend.byJournal', 1)
            ->where('spend.byJournal.0.journal', 'demo')
            // A blocked run cost nothing but is still counted — it is evidence of what was asked.
            ->where('spend.byJournal.0.blocked', 1)
        );
    }

    public function test_a_regular_user_cannot_reach_or_change_anything(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.ai.index'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.ai.key.update'), ['provider' => 'anthropic', 'key' => 'sk-x'])->assertForbidden();
        $this->actingAs($user)->post(route('admin.ai.test'), ['provider' => 'anthropic'])->assertForbidden();
        $this->actingAs($user)->put(route('admin.ai.switch'), ['enabled' => false])->assertForbidden();

        $this->assertNull(app(AiCredentials::class)->for('anthropic'));
        $this->assertNull($this->site->fresh()->ai_enabled);
    }

    public function test_a_guest_cannot_reach_the_screen(): void
    {
        $this->get(route('admin.ai.index'))->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------- provider routing

    public function test_models_route_to_the_right_provider(): void
    {
        $registry = app(ProviderRegistry::class);

        $this->assertSame('anthropic', $registry->providerNameFor('claude-haiku-4-5-20251001'));
        $this->assertSame('gemini', $registry->providerNameFor('gemini-2.5-flash'));
    }

    public function test_an_unroutable_model_fails_loudly_rather_than_guessing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/claude-\* or gemini-\*/');

        app(ProviderRegistry::class)->providerNameFor('gpt-4o');
    }

    public function test_a_gemini_model_is_actually_called_through_the_gemini_endpoint(): void
    {
        $journal = Journal::create(['site_id' => $this->site->id, 'path' => 'demo', 'title' => ['en' => 'Demo']]);
        $journal->settings()->create(['name' => 'ai_enabled', 'locale' => '', 'value' => true]);

        config()->set('ai.models.metadata_extraction', 'gemini-2.5-flash');
        app(AiCredentials::class)->put('gemini', 'AIza-test');

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '{"title":"A Paper"}']]]]],
            'usageMetadata' => ['promptTokenCount' => 1000, 'candidatesTokenCount' => 100],
        ])]);

        $result = app(AiRunner::class)->run(
            \Alpha\Journal\Enums\AiFeature::MetadataExtraction,
            $journal,
            'manuscript text',
            'extracted_metadata',
            ['type' => 'object'],
        );

        $this->assertSame('A Paper', $result['data']['title']);
        $this->assertSame(AiRunStatus::Succeeded, $result['run']->status);
        $this->assertSame(1000, $result['run']->input_tokens);
        // Gemini 2.5 Flash at 0.3/2.5 microcents per token: 1000*0.3 + 100*2.5 = 550.
        $this->assertSame(550, $result['run']->cost_microcents);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com')
            && $request->hasHeader('x-goog-api-key', 'AIza-test'));
    }

    public function test_the_gemini_key_never_travels_in_the_url(): void
    {
        app(AiCredentials::class)->put('gemini', 'AIza-secret');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => []])]);

        app(ProviderRegistry::class)->get('gemini')->ping();

        // A key in a query string lands in access logs, proxy logs and exception traces.
        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'AIza-secret'));
    }

    // ---------------------------------------------------------------- schema translation

    public function test_nullable_unions_are_translated_for_gemini(): void
    {
        $translated = GeminiProvider::toGeminiSchema([
            'type' => 'object',
            'properties' => [
                'title' => ['type' => ['string', 'null']],
                'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                'authors' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => ['family_name' => ['type' => ['string', 'null']]],
                    ],
                ],
            ],
            'required' => ['title'],
        ]);

        // Gemini rejects `type: ["string","null"]` outright; it wants a single type plus
        // `nullable`. Every optional field in the A1 schema is written the union way, so without
        // this translation the whole feature 400s on Gemini.
        $this->assertSame('string', $translated['properties']['title']['type']);
        $this->assertTrue($translated['properties']['title']['nullable']);

        // Non-nullable fields are left exactly as they were.
        $this->assertSame('string', $translated['properties']['keywords']['items']['type']);
        $this->assertArrayNotHasKey('nullable', $translated['properties']['keywords']['items']);

        // Translation recurses through array items into nested objects.
        $this->assertTrue($translated['properties']['authors']['items']['properties']['family_name']['nullable']);

        $this->assertSame(['title'], $translated['required']);
    }

    public function test_the_real_extraction_schema_survives_translation(): void
    {
        $translated = GeminiProvider::toGeminiSchema(\Alpha\Journal\Ai\MetadataExtractor::schema());

        $json = json_encode($translated);

        // The shape actually sent to Gemini must contain no union types anywhere.
        $this->assertStringNotContainsString('"null"', $json);
        $this->assertTrue($translated['properties']['abstract']['nullable']);
        $this->assertSame('string', $translated['properties']['confidence']['type']);
    }
}
