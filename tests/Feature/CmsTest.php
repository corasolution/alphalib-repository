<?php

namespace Tests\Feature;

use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The CMS section — landing-page content split out of the old single /site/settings form into
 * focused screens (homepage, menu, footer, hero slides, pages), all gated by SitePolicy::manage.
 *
 * The partial-save isolation tests are the point of this file: each screen submits only its own
 * fields, so an update that replaced Site.settings wholesale would silently wipe every setting
 * the current screen doesn't happen to post.
 */
class CmsTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create(['title' => 'Alpha Journal']);
        $this->admin = User::factory()->create(['is_site_admin' => true]);
    }

    /** Seeds every settings key so a partial save has something to accidentally destroy. */
    private function seedAllSettings(): void
    {
        $this->site->update([
            'settings' => [
                'tagline' => 'Original tagline',
                'about' => 'Original about text.',
                'footer_text' => 'Original footer.',
                'menu_items' => [['label' => 'Ethics', 'url' => '/pages/ethics']],
                'social_links' => ['facebook' => 'https://facebook.com/original'],
            ],
        ]);
    }

    public function test_saving_the_menu_does_not_wipe_other_settings(): void
    {
        $this->seedAllSettings();

        $response = $this->actingAs($this->admin)->put(route('cms.menu.update'), [
            'menu_items' => [['label' => 'Journals', 'url' => '/journals']],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $settings = $this->site->fresh()->settings;
        $this->assertSame('Journals', $settings['menu_items'][0]['label']);
        $this->assertSame('Original tagline', $settings['tagline']);
        $this->assertSame('Original about text.', $settings['about']);
        $this->assertSame('Original footer.', $settings['footer_text']);
        $this->assertSame('https://facebook.com/original', $settings['social_links']['facebook']);
    }

    public function test_saving_the_footer_does_not_wipe_other_settings(): void
    {
        $this->seedAllSettings();

        $this->actingAs($this->admin)->put(route('cms.footer.update'), [
            'footer_text' => 'Updated footer.',
            'social_links' => ['twitter' => 'https://x.com/updated'],
        ])->assertSessionHasNoErrors();

        $settings = $this->site->fresh()->settings;
        $this->assertSame('Updated footer.', $settings['footer_text']);
        $this->assertSame('https://x.com/updated', $settings['social_links']['twitter']);
        $this->assertSame('Original tagline', $settings['tagline']);
        $this->assertSame('Original about text.', $settings['about']);
        $this->assertSame('Ethics', $settings['menu_items'][0]['label']);
    }

    public function test_saving_the_homepage_does_not_wipe_other_settings(): void
    {
        $this->seedAllSettings();

        $this->actingAs($this->admin)->put(route('cms.homepage.update'), [
            'title' => 'Renamed Site',
            'tagline' => 'Updated tagline',
            'about' => 'Updated about.',
        ])->assertSessionHasNoErrors();

        $site = $this->site->fresh();
        $this->assertSame('Renamed Site', $site->title);
        $this->assertSame('Updated tagline', $site->settings['tagline']);
        $this->assertSame('Original footer.', $site->settings['footer_text']);
        $this->assertSame('Ethics', $site->settings['menu_items'][0]['label']);
        $this->assertSame('https://facebook.com/original', $site->settings['social_links']['facebook']);
    }

    public function test_clearing_a_field_on_its_own_screen_still_works(): void
    {
        $this->seedAllSettings();

        // Blanking the tagline must actually clear it — merge-safety must not mean
        // "you can never remove a value".
        $this->actingAs($this->admin)->put(route('cms.homepage.update'), [
            'title' => 'Alpha Journal',
            'tagline' => '',
            'about' => 'Still here.',
        ])->assertSessionHasNoErrors();

        $settings = $this->site->fresh()->settings;
        $this->assertArrayNotHasKey('tagline', $settings);
        $this->assertSame('Still here.', $settings['about']);
        $this->assertSame('Original footer.', $settings['footer_text']);
    }

    public function test_every_cms_screen_renders_for_a_site_admin(): void
    {
        Journal::create(['site_id' => $this->site->id, 'path' => 'demo', 'title' => ['en' => 'Demo']]);

        $screens = [
            'cms.homepage.edit' => 'cms/homepage',
            'cms.menu.edit' => 'cms/menu',
            'cms.footer.edit' => 'cms/footer',
            'cms.hero-slides.index' => 'cms/hero-slides',
            'cms.pages.index' => 'cms/pages/index',
        ];

        foreach ($screens as $routeName => $component) {
            $this->actingAs($this->admin)->get(route($routeName))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component($component));
        }
    }

    public function test_a_non_site_admin_is_forbidden_from_every_cms_screen(): void
    {
        $user = User::factory()->create(['is_site_admin' => false]);

        foreach (['cms.homepage.edit', 'cms.menu.edit', 'cms.footer.edit', 'cms.hero-slides.index', 'cms.pages.index'] as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertForbidden();
        }

        $this->actingAs($user)->put(route('cms.menu.update'), ['menu_items' => []])->assertForbidden();
        $this->actingAs($user)->put(route('cms.footer.update'), ['footer_text' => 'x'])->assertForbidden();
        $this->actingAs($user)->put(route('cms.homepage.update'), ['title' => 'Hijacked'])->assertForbidden();

        $this->assertSame('Alpha Journal', $this->site->fresh()->title);
    }

    public function test_the_old_settings_url_redirects_into_the_cms(): void
    {
        $response = $this->actingAs($this->admin)->get('/site/settings');

        $response->assertRedirect(route('cms.homepage.edit'));
    }

    public function test_the_public_site_still_resolves_after_the_route_rename(): void
    {
        $this->site->pages()->create(['slug' => 'ethics', 'title' => 'Ethics', 'body' => 'Policy.']);

        $this->get('/')->assertOk();
        $this->get('/journals')->assertOk();
        $this->get('/pages/ethics')->assertOk();
    }
}
