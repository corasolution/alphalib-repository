<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Models\HeroSlide;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The site-wide '/' homepage. `Site.redirect_journal_id` (already in the schema before this
 * feature) lets a single-journal install skip this page and go straight to that journal instead
 * — the exact case this app is currently deployed as.
 */
class SiteHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_lists_every_enabled_journal(): void
    {
        $site = Site::create(['title' => 'Alpha Journal']);
        Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal'], 'is_enabled' => true]);
        Journal::create(['site_id' => $site->id, 'path' => 'disabled', 'title' => ['en' => 'Disabled'], 'is_enabled' => false]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('site/home')
            ->has('journals', 1)
            ->where('journals.0.path', 'demo')
        );
    }

    public function test_setting_a_redirect_journal_sends_the_homepage_straight_there(): void
    {
        $site = Site::create(['title' => 'Alpha Journal']);
        $journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal'], 'is_enabled' => true]);
        $site->update(['redirect_journal_id' => $journal->id]);

        $response = $this->get('/');

        $response->assertRedirect(route('journal.public.show', ['journal' => 'demo']));
    }

    public function test_a_disabled_redirect_target_falls_back_to_the_homepage(): void
    {
        $site = Site::create(['title' => 'Alpha Journal']);
        $journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal'], 'is_enabled' => false]);
        $site->update(['redirect_journal_id' => $journal->id]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('site/home'));
    }

    public function test_a_site_admin_can_update_site_settings(): void
    {
        $site = Site::create(['title' => 'Alpha Journal']);
        $admin = User::factory()->create(['is_site_admin' => true]);

        $response = $this->actingAs($admin)->put(route('cms.homepage.update'), [
            'title' => 'Alpha Journal Network',
            'tagline' => 'Scholarly publishing for Southeast Asia',
            'about' => 'A network of peer-reviewed journals.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $site->refresh();
        $this->assertSame('Alpha Journal Network', $site->title);
        $this->assertSame('Scholarly publishing for Southeast Asia', $site->settings['tagline']);
    }

    public function test_a_guest_cannot_reach_the_settings_page(): void
    {
        Site::create(['title' => 'Alpha Journal']);

        $response = $this->get(route('cms.homepage.edit'));

        $response->assertRedirect(route('login'));
    }

    public function test_uploading_a_logo_persists_it_and_the_homepage_gets_a_working_url(): void
    {
        Storage::fake('r2');
        $site = Site::create(['title' => 'Alpha Journal']);
        $admin = User::factory()->create(['is_site_admin' => true]);

        $response = $this->actingAs($admin)->put(route('cms.homepage.update'), [
            'title' => 'Alpha Journal',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $site->refresh();
        $this->assertNotNull($site->logo_path);
        Storage::disk('r2')->assertExists($site->logo_path);

        $home = $this->get('/');
        $home->assertInertia(fn (AssertableInertia $page) => $page->where('site.logoUrl', fn ($url) => $url !== null));
    }

    public function test_menu_items_and_social_links_round_trip_through_the_homepage(): void
    {
        Site::create(['title' => 'Alpha Journal']);
        $admin = User::factory()->create(['is_site_admin' => true]);

        // Menu and footer are separate CMS screens now, so this is two saves — which also
        // exercises that the second one doesn't clobber the first.
        $this->actingAs($admin)->put(route('cms.menu.update'), [
            'menu_items' => [['label' => 'Guidelines', 'url' => 'https://example.com/guidelines']],
        ]);
        $this->actingAs($admin)->put(route('cms.footer.update'), [
            'footer_text' => 'Publishing since 2026.',
            'social_links' => ['facebook' => 'https://facebook.com/alphajournal', 'twitter' => ''],
        ]);

        $response = $this->get('/');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('site.footerText', 'Publishing since 2026.')
            ->has('menuItems', 1)
            ->where('menuItems.0.label', 'Guidelines')
            ->where('socialLinks.facebook', 'https://facebook.com/alphajournal')
            ->missing('socialLinks.twitter')
        );
    }

    public function test_a_regular_user_cannot_upload_a_logo_or_set_menu_items(): void
    {
        Storage::fake('r2');
        $site = Site::create(['title' => 'Alpha Journal']);
        $user = User::factory()->create(['is_site_admin' => false]);

        $this->actingAs($user)->put(route('cms.homepage.update'), [
            'title' => 'Hijacked',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertForbidden();

        $this->actingAs($user)->put(route('cms.menu.update'), [
            'menu_items' => [['label' => 'Evil', 'url' => 'https://evil.example']],
        ])->assertForbidden();

        $this->assertNull($site->fresh()->logo_path);
    }

    public function test_the_journals_index_lists_enabled_journals_with_their_published_article_counts(): void
    {
        $site = Site::create(['title' => 'Alpha Journal']);
        $journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal'], 'is_enabled' => true]);
        Journal::create(['site_id' => $site->id, 'path' => 'hidden', 'title' => ['en' => 'Hidden'], 'is_enabled' => false]);
        $journal->settings()->create(['name' => 'tagline', 'locale' => 'en', 'value' => 'A journal about things.']);

        // Published: counts. Still in production: must not count, even though its publication
        // row says 'published' — the same double-gate the public journal page relies on.
        $this->publishArticle($journal, SubmissionStage::Published);
        $this->publishArticle($journal, SubmissionStage::Production);

        $response = $this->get('/journals');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('site/journals')
            ->has('journals', 1)
            ->where('journals.0.path', 'demo')
            ->where('journals.0.description', 'A journal about things.')
            ->where('journals.0.articleCount', 1)
        );
    }

    public function test_the_journals_index_carries_the_header_menu(): void
    {
        $site = Site::create(['title' => 'Alpha Journal']);
        $site->update(['settings' => ['menu_items' => [['label' => 'Ethics', 'url' => '/pages/ethics']]]]);

        $response = $this->get('/journals');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('menuItems', 1)
            ->where('menuItems.0.label', 'Ethics')
        );
    }

    private function publishArticle(Journal $journal, SubmissionStage $stage): void
    {
        $submission = Submission::create([
            'journal_id' => $journal->id,
            'submitted_by' => User::factory()->create()->id,
            'stage' => $stage->value,
        ]);
        Publication::create([
            'submission_id' => $submission->id,
            'version' => 1,
            'title' => ['en' => 'An Article'],
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_empty_cms_settings_produce_an_empty_homepage_payload(): void
    {
        Site::create(['title' => 'Alpha Journal']);

        $response = $this->get('/');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('site.footerText', null)
            ->where('site.logoUrl', null)
            ->has('menuItems', 0)
            ->has('socialLinks', 0)
            ->has('heroSlides', 0)
        );
    }

    public function test_a_site_admin_can_add_a_hero_slide(): void
    {
        Storage::fake('r2');
        Site::create(['title' => 'Alpha Journal']);
        $admin = User::factory()->create(['is_site_admin' => true]);

        $response = $this->actingAs($admin)->post(route('cms.hero-slides.store'), [
            'image' => UploadedFile::fake()->image('slide.jpg', 1600, 600),
            'caption' => 'Our latest issue is out now.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $slide = HeroSlide::sole();
        Storage::disk('r2')->assertExists($slide->path);
        $this->assertSame('Our latest issue is out now.', $slide->caption);

        $home = $this->get('/');
        $home->assertInertia(fn (AssertableInertia $page) => $page
            ->has('heroSlides', 1)
            ->where('heroSlides.0.caption', 'Our latest issue is out now.')
        );
    }

    public function test_a_site_admin_can_remove_a_hero_slide(): void
    {
        Storage::fake('r2');
        $site = Site::create(['title' => 'Alpha Journal']);
        $slide = HeroSlide::storeFor($site, UploadedFile::fake()->image('slide.jpg'));
        $admin = User::factory()->create(['is_site_admin' => true]);

        $response = $this->actingAs($admin)->delete(route('cms.hero-slides.destroy', ['heroSlide' => $slide->id]));

        $response->assertRedirect();
        $this->assertSame(0, HeroSlide::count());
    }

    public function test_a_regular_user_cannot_add_or_remove_hero_slides(): void
    {
        Storage::fake('r2');
        $site = Site::create(['title' => 'Alpha Journal']);
        $slide = HeroSlide::storeFor($site, UploadedFile::fake()->image('slide.jpg'));
        $user = User::factory()->create(['is_site_admin' => false]);

        $this->actingAs($user)->post(route('cms.hero-slides.store'), ['image' => UploadedFile::fake()->image('sneaky.jpg')])
            ->assertForbidden();
        $this->assertSame(1, HeroSlide::count());

        $this->actingAs($user)->delete(route('cms.hero-slides.destroy', ['heroSlide' => $slide->id]))
            ->assertForbidden();
        $this->assertSame(1, HeroSlide::count());
    }
    public function test_highlight_cards_round_trip_through_the_cms_and_onto_the_homepage(): void
    {
        Site::create(['title' => 'Test Site']);
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->put(route('cms.homepage.update'), [
            'title' => 'Test Site',
            'highlights' => [
                ['title' => 'Open Access', 'body' => 'Freely readable.'],
                ['title' => 'Peer Review', 'body' => 'Every article.'],
            ],
        ])->assertSessionHasNoErrors();

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('highlights', 2)
            ->where('highlights.0.title', 'Open Access')
            ->where('highlights.1.body', 'Every article.')
        );
    }

    public function test_a_blank_card_slot_is_dropped_rather_than_rendered_empty(): void
    {
        Site::create(['title' => 'Test Site']);
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->put(route('cms.homepage.update'), [
            'title' => 'Test Site',
            'highlights' => [
                ['title' => 'Open Access', 'body' => 'Freely readable.'],
                ['title' => '', 'body' => ''],
                ['title' => 'Peer Review', 'body' => ''],
            ],
        ])->assertSessionHasNoErrors();

        // The empty middle slot must not survive as a numbered card with nothing in it — and the
        // third card must move up to position 2 rather than leaving a gap in the numbering.
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('highlights', 2)
            ->where('highlights.1.title', 'Peer Review')
            ->where('highlights.1.body', null)
        );
    }

    public function test_clearing_every_card_falls_back_to_the_about_text(): void
    {
        Site::create(['title' => 'Test Site']);
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->put(route('cms.homepage.update'), [
            'title' => 'Test Site',
            'about' => 'An intro paragraph.',
            'highlights' => [['title' => 'Open Access', 'body' => 'Freely readable.']],
        ]);

        $this->actingAs($admin)->put(route('cms.homepage.update'), [
            'title' => 'Test Site',
            'about' => 'An intro paragraph.',
            'highlights' => [['title' => '', 'body' => '']],
        ])->assertSessionHasNoErrors();

        // Turning the strip off must leave the homepage with its intro, not a blank band.
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('highlights', 0)
            ->where('site.about', 'An intro paragraph.')
        );
    }

    public function test_saving_highlights_does_not_wipe_the_other_homepage_settings(): void
    {
        Site::create(['title' => 'Test Site']);
        $admin = User::factory()->create(['is_site_admin' => true]);
        Site::firstOrFail()->update(['settings' => [
            'footer_text' => 'Keep me.',
            'menu_items' => [['label' => 'Ethics', 'url' => '/pages/ethics']],
        ]]);

        $this->actingAs($admin)->put(route('cms.homepage.update'), [
            'title' => 'Test Site',
            'highlights' => [['title' => 'Open Access', 'body' => 'Freely readable.']],
        ])->assertSessionHasNoErrors();

        // The same merge-safety every other CMS screen relies on.
        $settings = Site::firstOrFail()->settings;
        $this->assertSame('Keep me.', $settings['footer_text']);
        $this->assertCount(1, $settings['menu_items']);
    }

    public function test_more_cards_than_the_strip_holds_are_rejected(): void
    {
        Site::create(['title' => 'Test Site']);
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->put(route('cms.homepage.update'), [
            'title' => 'Test Site',
            'highlights' => array_fill(0, Site::HIGHLIGHT_SLOTS + 1, ['title' => 'x', 'body' => 'y']),
        ])->assertSessionHasErrors('highlights');
    }
}