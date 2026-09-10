<?php

namespace Tests\Feature\Admin;

use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\User;
use Alpha\Journal\Support\Secrets\StorageCredentials;
use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Admin → Storage: the screen that points an install at a bucket.
 *
 * Two things matter more than the rest. A stored secret must never come back to the browser, and
 * saving the form must not wipe a key the admin could not see to retype — the second is how a
 * working install silently loses its storage credentials while someone fixes a typo in a bucket
 * name.
 */
class StorageSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(FileStorage::LOCAL_DISK);
        Storage::fake(FileStorage::CLOUD_DISK);

        $this->site = Site::create(['title' => 'Test Site']);
        $this->admin = User::factory()->create(['is_site_admin' => true]);
    }

    private function connection(array $overrides = []): array
    {
        return array_merge([
            StorageCredentials::ACCESS_KEY_ID => 'AKIAEXAMPLE',
            StorageCredentials::SECRET_ACCESS_KEY => 'super-secret-value',
            StorageCredentials::BUCKET => 'alpha-journal',
            StorageCredentials::ENDPOINT => 'https://acct.r2.cloudflarestorage.com',
            StorageCredentials::REGION => 'auto',
            StorageCredentials::URL => '',
        ], $overrides);
    }

    public function test_a_fresh_install_reports_that_it_writes_to_the_local_disk(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/storage')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/storage')
                ->where('configured', false)
                ->where('writingTo', 'local'));
    }

    public function test_saving_a_connection_switches_new_uploads_to_the_bucket(): void
    {
        $this->actingAs($this->admin)->put('/admin/storage', $this->connection())->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get('/admin/storage')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('configured', true)
                ->where('writingTo', 'cloud'));
    }

    public function test_the_stored_secret_never_comes_back_to_the_browser(): void
    {
        $this->actingAs($this->admin)->put('/admin/storage', $this->connection());

        $response = $this->actingAs($this->admin)->get('/admin/storage');

        $response->assertDontSee('super-secret-value');
        $response->assertDontSee('AKIAEXAMPLE');
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('secrets.1.set', true)
            ->where('secrets.1.hint', '…alue'));
    }

    /**
     * The form cannot show a saved secret, so it always posts an empty one. Treating that as
     * "delete it" would disconnect the bucket every time someone corrected the region.
     */
    public function test_resaving_without_retyping_the_secret_keeps_it(): void
    {
        $this->actingAs($this->admin)->put('/admin/storage', $this->connection());

        $this->actingAs($this->admin)->put('/admin/storage', $this->connection([
            StorageCredentials::ACCESS_KEY_ID => '',
            StorageCredentials::SECRET_ACCESS_KEY => '',
            StorageCredentials::REGION => 'apac',
        ]))->assertSessionHasNoErrors();

        $credentials = app(StorageCredentials::class);
        $this->assertSame('super-secret-value', $credentials->for(StorageCredentials::SECRET_ACCESS_KEY));
        $this->assertSame('apac', $credentials->for(StorageCredentials::REGION));
        $this->assertTrue($credentials->isConfigured());
    }

    public function test_a_secret_can_still_be_removed_deliberately(): void
    {
        $this->actingAs($this->admin)->put('/admin/storage', $this->connection());

        $this->actingAs($this->admin)
            ->delete('/admin/storage/secret', ['field' => StorageCredentials::SECRET_ACCESS_KEY])
            ->assertSessionHasNoErrors();

        $this->assertFalse(app(StorageCredentials::class)->isConfigured());
    }

    public function test_a_field_that_is_not_a_secret_cannot_be_removed_through_that_route(): void
    {
        $this->actingAs($this->admin)->put('/admin/storage', $this->connection());

        $this->actingAs($this->admin)
            ->delete('/admin/storage/secret', ['field' => 'settings'])
            ->assertSessionHasErrors('field');
    }

    public function test_an_endpoint_that_is_not_a_url_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/storage', $this->connection([StorageCredentials::ENDPOINT => 'not-a-url']))
            ->assertSessionHasErrors(StorageCredentials::ENDPOINT);
    }

    public function test_migrating_is_refused_until_a_bucket_is_configured(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/storage/migrate')
            ->assertSessionHasErrors('storage');
    }

    public function test_a_non_admin_cannot_see_or_change_storage_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/storage')->assertForbidden();
        $this->actingAs($user)->put('/admin/storage', $this->connection())->assertForbidden();
        $this->actingAs($user)->post('/admin/storage/migrate')->assertForbidden();
        $this->actingAs($user)->post('/admin/storage/test')->assertForbidden();

        $this->assertNull($this->site->fresh()->storage_credentials);
    }

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $this->get('/admin/storage')->assertRedirect('/login');
    }
}
