<?php

namespace Tests\Feature;

use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\SitePage;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Static content pages — the real destinations for header menu links (Editorial Board,
 * Submission Guidelines, Ethics, Contact, ...) instead of everything pointing back at the
 * journal homepage as a placeholder. Same authority as every other site-level CMS surface
 * this session: SitePolicy::manage (is_site_admin).
 */
class SitePageTest extends TestCase
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

    public function test_a_site_admin_can_create_a_page_with_an_auto_generated_slug(): void
    {
        $response = $this->actingAs($this->admin)->post(route('cms.pages.store'), [
            'title' => 'Editorial Board',
            'body' => 'Our editors are drawn from universities across the region.',
        ]);

        $response->assertRedirect(route('cms.pages.index'));

        $page = SitePage::sole();
        $this->assertSame('editorial-board', $page->slug);
        $this->assertSame($this->site->id, $page->site_id);
    }

    public function test_a_custom_slug_is_respected(): void
    {
        $this->actingAs($this->admin)->post(route('cms.pages.store'), [
            'title' => 'Contact Us',
            'slug' => 'contact',
            'body' => 'Email us at contact@example.com.',
        ]);

        $this->assertSame('contact', SitePage::sole()->slug);
    }

    public function test_slugs_must_be_unique_per_site(): void
    {
        SitePage::create(['site_id' => $this->site->id, 'slug' => 'ethics', 'title' => 'Ethics']);

        $response = $this->actingAs($this->admin)->post(route('cms.pages.store'), [
            'title' => 'Ethics Policy',
            'slug' => 'ethics',
        ]);

        $response->assertSessionHasErrors('slug');
        $this->assertSame(1, SitePage::count());
    }

    public function test_a_guest_can_view_a_published_page(): void
    {
        SitePage::create([
            'site_id' => $this->site->id,
            'slug' => 'ethics',
            'title' => 'Ethics',
            'body' => 'Our publication ethics policy.',
        ]);

        $response = $this->get('/pages/ethics');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('site/pages/show')
            ->where('page.title', 'Ethics')
            ->where('page.body', 'Our publication ethics policy.')
        );
    }

    public function test_an_unknown_slug_404s(): void
    {
        $response = $this->get('/pages/does-not-exist');

        $response->assertNotFound();
    }

    public function test_a_site_admin_can_update_a_page(): void
    {
        $page = SitePage::create(['site_id' => $this->site->id, 'slug' => 'ethics', 'title' => 'Ethics']);

        $response = $this->actingAs($this->admin)->put(route('cms.pages.update', ['page' => $page->id]), [
            'title' => 'Publication Ethics',
            'slug' => 'ethics',
            'body' => 'Updated policy text.',
        ]);

        $response->assertRedirect(route('cms.pages.index'));
        $this->assertSame('Publication Ethics', $page->fresh()->title);
    }

    public function test_a_site_admin_can_delete_a_page(): void
    {
        $page = SitePage::create(['site_id' => $this->site->id, 'slug' => 'ethics', 'title' => 'Ethics']);

        $response = $this->actingAs($this->admin)->delete(route('cms.pages.destroy', ['page' => $page->id]));

        $response->assertRedirect(route('cms.pages.index'));
        $this->assertSame(0, SitePage::count());
    }

    public function test_a_regular_user_cannot_manage_pages(): void
    {
        $user = User::factory()->create(['is_site_admin' => false]);
        $page = SitePage::create(['site_id' => $this->site->id, 'slug' => 'ethics', 'title' => 'Ethics']);

        $this->actingAs($user)->get(route('cms.pages.index'))->assertForbidden();
        $this->actingAs($user)->post(route('cms.pages.store'), ['title' => 'Hijack'])->assertForbidden();
        $this->actingAs($user)->put(route('cms.pages.update', ['page' => $page->id]), ['title' => 'Hijack', 'slug' => 'ethics'])->assertForbidden();
        $this->actingAs($user)->delete(route('cms.pages.destroy', ['page' => $page->id]))->assertForbidden();

        $this->assertSame('Ethics', $page->fresh()->title);
    }
    public function test_a_blank_slug_is_generated_from_the_title(): void
    {
        $admin = User::factory()->create(['is_site_admin' => true]);
        Site::firstOrCreate([], ['title' => 'Test Site']);

        $this->actingAs($admin)->post(route('cms.pages.store'), [
            'title' => 'Editorial Board',
            'slug' => '',
            'body' => 'Who runs this journal.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('editorial-board', SitePage::sole()->slug);
    }

    public function test_a_title_with_no_latin_characters_still_gets_a_usable_slug(): void
    {
        $admin = User::factory()->create(['is_site_admin' => true]);
        Site::firstOrCreate([], ['title' => 'Test Site']);

        // Str::slug strips anything it cannot transliterate, so a Khmer title produced an empty
        // string — and the page was created at /pages/ with a slug that collided with the next
        // one. This app fully supports Khmer, so that was a live trap, not a hypothetical.
        $this->actingAs($admin)->post(route('cms.pages.store'), [
            'title' => 'ទស្សនាវដ្ដីកសិកម្ម',
            'slug' => '',
        ])->assertSessionHasNoErrors();

        $slug = SitePage::sole()->slug;
        $this->assertNotSame('', $slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
    }

    public function test_two_untranslatable_titles_do_not_collide(): void
    {
        $admin = User::factory()->create(['is_site_admin' => true]);
        Site::firstOrCreate([], ['title' => 'Test Site']);

        foreach (['ទស្សនាវដ្ដីកសិកម្ម', 'ក្រមសីលធម៌'] as $title) {
            $this->actingAs($admin)->post(route('cms.pages.store'), ['title' => $title, 'slug' => ''])
                ->assertSessionHasNoErrors();
        }

        // The fallback is deliberately generic, so uniqueness has to be resolved rather than
        // left to a unique-constraint failure about a field the author never filled in.
        $slugs = SitePage::pluck('slug');
        $this->assertCount(2, $slugs->unique(), "Slugs collided: {$slugs->implode(', ')}");
    }

    public function test_an_explicit_slug_is_respected(): void
    {
        $admin = User::factory()->create(['is_site_admin' => true]);
        Site::firstOrCreate([], ['title' => 'Test Site']);

        $this->actingAs($admin)->post(route('cms.pages.store'), [
            'title' => 'Editorial Board',
            'slug' => 'our-team',
        ])->assertSessionHasNoErrors();

        // Auto-generation must never override a slug somebody chose deliberately.
        $this->assertSame('our-team', SitePage::sole()->slug);
    }
}