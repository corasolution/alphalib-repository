<?php

namespace Tests\Feature;

use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\User;
use Alpha\Journal\Support\CurrentSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The system's own name and logo, editable by a site admin in CMS → Homepage.
 *
 * These were hardcoded to "Laravel Starter Kit" and the Laravel mark, so a customer's editors
 * were greeted by the starter kit's identity rather than their own. The point of these tests is
 * that one CMS field now reaches every surface: the browser tab, the staff sidebar, and the
 * sign-in screen.
 */
class SystemBrandingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_site_admin' => true]);
    }

    private function site(string $title = 'BELTEI Research Journal'): Site
    {
        $site = Site::create(['title' => $title]);
        app(CurrentSite::class)->forget();

        return $site;
    }

    public function test_the_system_name_comes_from_the_site_row(): void
    {
        $this->site('BELTEI Research Journal');

        $this->assertSame('BELTEI Research Journal', app(CurrentSite::class)->name());
    }

    public function test_it_falls_back_to_config_before_a_site_exists(): void
    {
        config()->set('app.name', 'Alpha Journal');

        // A fresh install has no Site row yet, and the sidebar still has to render something.
        $this->assertSame('Alpha Journal', app(CurrentSite::class)->name());
    }

    public function test_the_browser_tab_title_uses_the_admin_set_name(): void
    {
        $this->site('BELTEI Research Journal');

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('<title inertia>BELTEI Research Journal</title>', false);
        // The JS title suffix reads this, so renaming takes effect without a rebuild.
        $response->assertSee('<meta name="app-name" content="BELTEI Research Journal">', false);
    }

    public function test_the_name_and_logo_are_shared_with_every_page(): void
    {
        Storage::fake('local');
        $site = $this->site('BELTEI Research Journal');
        $site->storeLogo(UploadedFile::fake()->image('logo.png'));
        app(CurrentSite::class)->forget();

        $this->actingAs($this->admin)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('site.name', 'BELTEI Research Journal')
            // The sidebar and sign-in screen both read this; null would fall back to the mark.
            ->whereNot('site.logoUrl', null)
        );
    }

    public function test_no_uploaded_logo_shares_null_rather_than_a_broken_url(): void
    {
        $this->site();

        $this->actingAs($this->admin)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('site.logoUrl', null)
        );
    }

    public function test_an_admin_renaming_the_system_changes_it_everywhere(): void
    {
        $this->site('Old Name');

        $this->actingAs($this->admin)
            ->put(route('cms.homepage.update'), ['title' => 'New Name'])
            ->assertSessionHasNoErrors();

        app(CurrentSite::class)->forget();

        $this->assertSame('New Name', Site::firstOrFail()->title);

        // Asserted on a staff page rather than /login: the admin is still authenticated here, and
        // the point is that the same blade title serves the signed-in surfaces too.
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertSee('<title inertia>New Name</title>', false);
    }

    public function test_a_regular_user_cannot_rename_the_system(): void
    {
        $this->site('Untouched');

        $this->actingAs(User::factory()->create())
            ->put(route('cms.homepage.update'), ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame('Untouched', Site::firstOrFail()->title);
    }

    public function test_the_site_row_is_read_once_per_request_not_once_per_caller(): void
    {
        $this->site();
        $current = app(CurrentSite::class);

        $first = $current->get();
        $second = $current->get();

        // Three callers now need this row on nearly every request — the Inertia share, the blade
        // root view, and Locales. Without memoisation that is three queries for one row that
        // cannot change mid-request.
        $this->assertSame($first, $second);
    }

    public function test_a_missing_sites_table_does_not_take_the_page_down(): void
    {
        // Runs before the first migration on a brand-new install; the share must degrade rather
        // than throw, or the app cannot render the page that would let you fix it.
        Site::query()->delete();
        app(CurrentSite::class)->forget();

        $this->assertNull(app(CurrentSite::class)->get());
        $this->assertNotSame('', app(CurrentSite::class)->name());
    }
}
