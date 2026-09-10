<?php

namespace Tests\Feature;

use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\User;
use Alpha\Journal\Support\DepositSettings;
use Alpha\Journal\Support\Secrets\IntegrationCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Admin → Indexing.
 *
 * The endpoint tests are the ones that earn their place. Crossref and DataCite both run a sandbox
 * that accepts a deposit and registers nothing, so an install pointed at the wrong one looks
 * healthy indefinitely — the failure only surfaces when somebody clicks a DOI months later. That
 * makes "test unless explicitly switched to live" a behaviour worth pinning down.
 */
class IndexingSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Site::create(['title' => 'BIU Journal']);
        $this->admin = User::factory()->create(['is_site_admin' => true]);
    }

    public function test_only_a_site_admin_can_open_it(): void
    {
        $this->get('/admin/indexing')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())->get('/admin/indexing')->assertForbidden();

        $this->actingAs($this->admin)->get('/admin/indexing')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/indexing'));
    }

    public function test_deposits_go_to_the_test_endpoint_until_live_is_chosen(): void
    {
        $settings = app(DepositSettings::class);

        $this->assertFalse($settings->crossrefIsLive());
        $this->assertSame(DepositSettings::CROSSREF_TEST_URL, $settings->crossrefDepositUrl());
        $this->assertSame(DepositSettings::DATACITE_TEST_URL, $settings->dataciteBaseUrl());
    }

    public function test_choosing_live_moves_the_endpoint(): void
    {
        $this->actingAs($this->admin)->put('/admin/indexing', [
            'crossref_username' => 'biu',
            'crossref_password' => 'secret',
            'crossref_live' => true,
            'datacite_live' => true,
        ])->assertRedirect();

        $settings = app(DepositSettings::class);

        $this->assertSame(DepositSettings::CROSSREF_LIVE_URL, $settings->crossrefDepositUrl());
        $this->assertSame(DepositSettings::DATACITE_LIVE_URL, $settings->dataciteBaseUrl());
    }

    public function test_saving_stores_the_credentials_encrypted_and_never_returns_them(): void
    {
        $this->actingAs($this->admin)->put('/admin/indexing', [
            'crossref_username' => 'biu-depositor',
            'crossref_password' => 'super-secret-value',
            'crossref_live' => false,
            'datacite_live' => false,
        ]);

        $this->assertSame('super-secret-value', app(IntegrationCredentials::class)->for(IntegrationCredentials::CROSSREF_PASSWORD));

        // The raw column must not hold it in the clear, and the screen must not hand it back.
        $this->assertStringNotContainsString('super-secret-value', (string) \DB::table('sites')->value('integration_credentials'));

        $this->actingAs($this->admin)->get('/admin/indexing')
            ->assertOk()
            ->assertDontSee('super-secret-value')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('crossref.hint', '…alue'));
    }

    public function test_a_blank_password_leaves_the_saved_one_alone(): void
    {
        $credentials = app(IntegrationCredentials::class);
        $credentials->put(IntegrationCredentials::CROSSREF_PASSWORD, 'original');

        // Editing the depositor email means submitting a form whose password box is empty, because
        // the screen never populates it. That must not wipe the credential.
        $this->actingAs($this->admin)->put('/admin/indexing', [
            'crossref_depositor_email' => 'ojs@biu.edu.kh',
            'crossref_password' => '',
            'crossref_live' => false,
            'datacite_live' => false,
        ])->assertRedirect();

        $this->assertSame('original', $credentials->for(IntegrationCredentials::CROSSREF_PASSWORD));
    }

    public function test_removing_clears_only_that_agency(): void
    {
        $credentials = app(IntegrationCredentials::class);
        $credentials->put(IntegrationCredentials::CROSSREF_PASSWORD, 'crossref-secret');
        $credentials->put(IntegrationCredentials::DATACITE_PASSWORD, 'datacite-secret');

        $this->actingAs($this->admin)->delete('/admin/indexing/secret', ['service' => 'crossref'])->assertRedirect();

        $this->assertNull($credentials->for(IntegrationCredentials::CROSSREF_PASSWORD));
        $this->assertSame('datacite-secret', $credentials->for(IntegrationCredentials::DATACITE_PASSWORD));
    }

    public function test_it_will_not_test_credentials_that_are_not_set(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/admin/indexing/test', ['service' => 'crossref'])
            ->assertOk()
            ->assertJson(['ok' => false]);
    }

    public function test_an_unknown_service_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/admin/indexing/test', ['service' => 'sci-hub'])
            ->assertStatus(422);
    }
}
