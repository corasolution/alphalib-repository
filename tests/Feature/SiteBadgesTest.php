<?php

namespace Tests\Feature;

use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\SiteBadge;
use Alpha\Journal\Models\User;
use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The "Indexed by" and "Tools" strips.
 *
 * The behaviour worth defending is that nothing appears unless somebody put it there. "Indexed by
 * X" is a factual claim to authors about where their work will be discoverable, and a strip that
 * seeded itself with plausible-looking services would be making that claim on the journal's
 * behalf — which is exactly what gets a journal flagged as predatory.
 */
class SiteBadgesTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(FileStorage::LOCAL_DISK);

        $this->site = Site::create(['title' => 'Test Site']);
        $this->admin = User::factory()->create(['is_site_admin' => true]);
    }

    private function logo(): UploadedFile
    {
        return UploadedFile::fake()->image('crossref.png', 200, 80);
    }

    public function test_a_fresh_install_has_no_badges_and_shows_no_strip(): void
    {
        $this->assertSame(0, SiteBadge::count());

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('siteBadges', []));
    }

    public function test_an_admin_can_add_an_entry_with_a_logo(): void
    {
        $this->actingAs($this->admin)->post('/cms/badges', [
            'group' => SiteBadge::GROUP_INDEXED_BY,
            'label' => 'Crossref',
            'url' => 'https://www.crossref.org',
            'logo' => $this->logo(),
        ])->assertSessionHasNoErrors();

        $badge = SiteBadge::sole();

        $this->assertSame('Crossref', $badge->label);
        $this->assertSame(SiteBadge::GROUP_INDEXED_BY, $badge->group);
        Storage::disk(FileStorage::LOCAL_DISK)->assertExists($badge->path);
        $this->assertNotNull($badge->logoUrl());
    }

    /**
     * Brand assets arrive later than the decision to list a service. A name on its own is still
     * true and still useful, so the logo is optional and the public side renders a text chip.
     */
    public function test_an_entry_without_a_logo_is_allowed(): void
    {
        $this->actingAs($this->admin)->post('/cms/badges', [
            'group' => SiteBadge::GROUP_TOOLS,
            'label' => 'Zotero',
        ])->assertSessionHasNoErrors();

        $badge = SiteBadge::sole();

        $this->assertNull($badge->path);
        $this->assertNull($badge->logoUrl());
    }

    public function test_entries_reach_every_public_page_grouped_by_strip(): void
    {
        SiteBadge::storeFor($this->site, SiteBadge::GROUP_INDEXED_BY, 'Crossref', 'https://www.crossref.org', null);
        SiteBadge::storeFor($this->site, SiteBadge::GROUP_TOOLS, 'Zotero', null, null);

        // Shared from the middleware rather than per-controller, so it has to be on all of them.
        foreach (['/', '/journals'] as $url) {
            $this->get($url)->assertInertia(fn (AssertableInertia $page) => $page
                ->has('siteBadges.indexed_by', 1)
                ->has('siteBadges.tools', 1)
                ->where('siteBadges.indexed_by.0.label', 'Crossref')
                ->where('siteBadges.tools.0.label', 'Zotero'));
        }
    }

    public function test_entries_keep_the_order_they_were_added_in(): void
    {
        foreach (['OCLC WorldCat', 'Google Scholar', 'ISSN'] as $label) {
            SiteBadge::storeFor($this->site, SiteBadge::GROUP_INDEXED_BY, $label, null, null);
        }

        $this->assertSame(
            ['OCLC WorldCat', 'Google Scholar', 'ISSN'],
            SiteBadge::where('group', SiteBadge::GROUP_INDEXED_BY)->orderBy('sort_order')->pluck('label')->all(),
        );
    }

    public function test_a_made_up_group_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post('/cms/badges', ['group' => 'awards', 'label' => 'Nobel Prize'])
            ->assertSessionHasErrors('group');

        $this->assertSame(0, SiteBadge::count());
    }

    public function test_a_link_that_is_not_a_url_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post('/cms/badges', ['group' => SiteBadge::GROUP_TOOLS, 'label' => 'Zotero', 'url' => 'zotero'])
            ->assertSessionHasErrors('url');
    }

    public function test_a_logo_can_be_replaced_without_losing_the_label_or_link(): void
    {
        $badge = SiteBadge::storeFor($this->site, SiteBadge::GROUP_INDEXED_BY, 'Crossref', 'https://www.crossref.org', null);

        $this->actingAs($this->admin)->post("/cms/badges/{$badge->id}", [
            'label' => 'Crossref',
            'url' => 'https://www.crossref.org',
            'logo' => $this->logo(),
        ])->assertSessionHasNoErrors();

        $badge->refresh();

        $this->assertSame('Crossref', $badge->label);
        $this->assertSame('https://www.crossref.org', $badge->url);
        Storage::disk(FileStorage::LOCAL_DISK)->assertExists($badge->path);
    }

    public function test_an_admin_can_remove_an_entry(): void
    {
        $badge = SiteBadge::storeFor($this->site, SiteBadge::GROUP_TOOLS, 'Zotero', null, null);

        $this->actingAs($this->admin)->delete("/cms/badges/{$badge->id}")->assertSessionHasNoErrors();

        $this->assertSame(0, SiteBadge::count());
    }

    public function test_a_non_admin_cannot_touch_the_strips(): void
    {
        $badge = SiteBadge::storeFor($this->site, SiteBadge::GROUP_TOOLS, 'Zotero', null, null);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/cms/badges')->assertForbidden();
        $this->actingAs($user)->post('/cms/badges', ['group' => SiteBadge::GROUP_TOOLS, 'label' => 'Nope'])->assertForbidden();
        $this->actingAs($user)->delete("/cms/badges/{$badge->id}")->assertForbidden();

        $this->assertSame(1, SiteBadge::count());
    }

    public function test_the_screen_offers_suggestions_without_creating_anything(): void
    {
        $this->actingAs($this->admin)
            ->get('/cms/badges')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('cms/badges')
                ->has('suggestions.indexed_by')
                ->has('suggestions.tools'));

        // A suggestion is a quick-fill for a form, never a row. Nothing claims to index this
        // journal until an admin says so.
        $this->assertSame(0, SiteBadge::count());
    }
}
