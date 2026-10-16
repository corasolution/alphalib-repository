<?php

namespace Tests\Feature;

use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The site's own text — title, tagline, about, highlight cards, footer, menu labels — in Khmer.
 *
 * Until this existed the language switch translated the interface but left every word an admin had
 * typed in English, so a Khmer visitor saw a half-translated homepage.
 */
class SiteTranslationTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create([
            'title' => 'Law Journal',
            'primary_locale' => 'en',
            'installed_locales' => ['en', 'km'],
            'settings' => [
                'tagline' => 'Open scholarship',
                'about' => 'About us',
                'footer_text' => 'Footer',
                'highlights' => [['title' => 'Open Access', 'body' => 'Body one'], ['title' => 'Peer Review', 'body' => 'Body two']],
                'menu_items' => [['label' => 'Journals', 'url' => '/journals'], ['label' => 'Contact', 'url' => '/pages/contact']],
                'translations' => ['km' => [
                    'title' => 'ទស្សនាវដ្ដីច្បាប់',
                    'tagline' => 'ការស្រាវជ្រាវបើកចំហ',
                    'highlights' => [['title' => 'ចូលដំណើរការដោយសេរី', 'body' => null]],
                    'menu_labels' => ['/journals' => 'ទស្សនាវដ្ដី'],
                ]],
            ],
        ]);
        Journal::create(['site_id' => $this->site->id, 'path' => 'demo', 'title' => ['en' => 'Demo'], 'is_enabled' => true]);
    }

    public function test_english_readers_see_the_primary_text(): void
    {
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('site.title', 'Law Journal')
            ->where('site.tagline', 'Open scholarship')
            ->where('menuItems.0.label', 'Journals')
            ->where('highlights.0.title', 'Open Access')
        );
    }

    public function test_khmer_readers_see_the_translation_and_fall_back_field_by_field(): void
    {
        $this->withSession(['locale' => 'km'])->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('site.title', 'ទស្សនាវដ្ដីច្បាប់')
            ->where('site.tagline', 'ការស្រាវជ្រាវបើកចំហ')
            // No Khmer about or footer was entered, so those keep the English text.
            ->where('site.about', 'About us')
            ->where('site.footerText', 'Footer')
            // The menu is translated per item; an untranslated item keeps its English label.
            ->where('menuItems.0.label', 'ទស្សនាវដ្ដី')
            ->where('menuItems.1.label', 'Contact')
            // A card with a Khmer heading but no Khmer body keeps the English body.
            ->where('highlights.0.title', 'ចូលដំណើរការដោយសេរី')
            ->where('highlights.0.body', 'Body one')
            ->where('highlights.1.title', 'Peer Review')
        );
    }

    public function test_other_public_pages_follow_the_language_too(): void
    {
        $this->withSession(['locale' => 'km'])->get('/journals')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('site.title', 'ទស្សនាវដ្ដីច្បាប់')
            ->where('menuItems.0.label', 'ទស្សនាវដ្ដី')
        );
    }

    public function test_an_untranslated_site_is_unchanged_in_khmer(): void
    {
        $this->site->update(['settings' => ['tagline' => 'Open scholarship']]);

        $this->withSession(['locale' => 'km'])->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('site.title', 'Law Journal')
            ->where('site.tagline', 'Open scholarship')
        );
    }

    public function test_an_admin_saves_and_clears_the_khmer_text_without_touching_the_english(): void
    {
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->put('/cms/homepage', [
            'title' => 'Law Journal',
            'tagline' => 'Open scholarship',
            'km' => [
                'title' => 'ចំណងជើង',
                'tagline' => 'ពាក្យស្លោក',
                'about' => 'អំពី',
                'highlights' => [['title' => 'មួយ', 'body' => 'ខ្លឹមសារ'], ['title' => '', 'body' => ''], ['title' => '', 'body' => '']],
            ],
        ])->assertSessionHasNoErrors();

        $settings = $this->site->fresh()->settings;
        $this->assertSame('ចំណងជើង', $settings['translations']['km']['title']);
        $this->assertSame('មួយ', $settings['translations']['km']['highlights'][0]['title']);
        $this->assertSame('Open scholarship', $settings['tagline'], 'The English text must be untouched.');
        // Saving the homepage must not wipe what the menu and footer screens stored.
        $this->assertSame('ទស្សនាវដ្ដី', $settings['translations']['km']['menu_labels']['/journals']);

        $this->actingAs($admin)->put('/cms/homepage', ['title' => 'Law Journal', 'km' => ['title' => '', 'tagline' => '', 'about' => '']])
            ->assertSessionHasNoErrors();

        $this->assertArrayNotHasKey('title', $this->site->fresh()->settings['translations']['km']);
    }

    public function test_menu_labels_are_saved_by_url_and_the_footer_translation_round_trips(): void
    {
        $admin = User::factory()->create(['is_site_admin' => true]);

        $this->actingAs($admin)->put('/cms/menu', ['menu_items' => [
            ['label' => 'Contact', 'label_km' => 'ទំនាក់ទំនង', 'url' => '/pages/contact'],
            ['label' => 'Journals', 'label_km' => '', 'url' => '/journals'],
        ]])->assertSessionHasNoErrors();

        $settings = $this->site->fresh()->settings;
        $this->assertSame(['/pages/contact' => 'ទំនាក់ទំនង'], $settings['translations']['km']['menu_labels']);
        $this->assertSame([['label' => 'Contact', 'url' => '/pages/contact'], ['label' => 'Journals', 'url' => '/journals']], $settings['menu_items']);

        $this->actingAs($admin)->put('/cms/footer', ['footer_text' => 'Footer', 'footer_text_km' => 'បាតទំព័រ'])->assertSessionHasNoErrors();
        $this->assertSame('បាតទំព័រ', $this->site->fresh()->settings['translations']['km']['footer_text']);

        $this->actingAs($admin)->get('/cms/menu')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('menuItems.0.label_km', 'ទំនាក់ទំនង')
            ->where('menuItems.1.label_km', '')
        );
    }
}
