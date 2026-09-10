<?php

namespace Tests\Feature;

use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\User;
use Alpha\Journal\Support\CurrentSite;
use Alpha\Journal\Support\SiteTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The five landing-page templates and the CMS → Appearance screen that selects between them.
 *
 * The load-bearing tests here are the two that guard against silent breakage rather than the ones
 * that prove the feature works: an unconfigured site must still render exactly the original design
 * (upgrading must not repaint anybody's site), and a palette override must be strict hex, because
 * these values are written into a <style> block in the document head.
 */
class AppearanceTest extends TestCase
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

    /**
     * Drops the memoised Site.
     *
     * CurrentSite caches the row for the life of the container, which in production is one
     * request but in a feature test is the whole test method — so without this, every assertion
     * after the first would read the settings as they were before the update.
     */
    private function reload(): void
    {
        app(CurrentSite::class)->forget();
    }

    private function theme(): SiteTheme
    {
        $this->reload();

        return app(SiteTheme::class);
    }

    public function test_an_unconfigured_site_gets_the_original_design(): void
    {
        $this->reload();
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('site/home')
            ->where('template', 'academic'));

        // Pinned, not read from the constant: the point is that these exact colours — the ones the
        // site had before templates existed — still come out.
        $palette = $this->theme()->palette();
        $this->assertSame('#005a9e', $palette['primary']);
        $this->assertSame('#004277', $palette['primary-dark']);
    }

    public function test_each_template_persists_and_renders(): void
    {
        foreach (array_keys(SiteTheme::TEMPLATES) as $key) {
            $this->actingAs($this->admin)
                ->put('/cms/appearance', ['template' => $key, 'font' => 'academic'])
                ->assertRedirect();

            $this->reload();
            $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('template', $key));

            $this->assertSame(
                SiteTheme::TEMPLATES[$key]['palette']['primary'],
                $this->theme()->palette()['primary'],
                "Template {$key} did not bring its own palette.",
            );
        }
    }

    public function test_an_unknown_stored_template_falls_back_rather_than_rendering_nothing(): void
    {
        // Reachable if a template key is ever renamed or removed while a site still points at it.
        $this->site->update(['settings' => ['template' => 'no-such-template']]);

        $this->reload();
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('template', 'academic'));
    }

    public function test_colour_overrides_layer_over_the_template_and_survive_a_template_switch(): void
    {
        $this->actingAs($this->admin)
            ->put('/cms/appearance', [
                'template' => 'academic',
                'font' => 'academic',
                'palette_overrides' => ['primary' => '#B22222'],
            ])
            ->assertRedirect();

        $palette = $this->theme()->palette();
        $this->assertSame('#b22222', $palette['primary'], 'Override was not applied.');
        $this->assertSame('#004277', $palette['primary-dark'], 'Untouched tokens must keep the template value.');

        $this->actingAs($this->admin)
            ->put('/cms/appearance', [
                'template' => 'bold',
                'font' => 'academic',
                'palette_overrides' => ['primary' => '#B22222'],
            ]);

        $palette = $this->theme()->palette();
        $this->assertSame('#b22222', $palette['primary'], 'A deliberate brand colour must survive switching design.');
        $this->assertSame('#3f1580', $palette['primary-dark'], 'The rest of the palette must follow the new template.');
    }

    public function test_a_malformed_colour_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->put('/cms/appearance', [
                'template' => 'academic',
                'font' => 'academic',
                'palette_overrides' => ['primary' => 'red; } body { display: none } :root {'],
            ])
            ->assertSessionHasErrors('palette_overrides.primary');

        $this->assertArrayNotHasKey('palette_overrides', $this->site->fresh()->settings ?? []);
    }

    public function test_the_rendered_css_block_only_ever_contains_hex(): void
    {
        // Belt and braces: even if a bad value reached settings by another route (a seeder, a
        // console command, a future screen), it must not escape into the <style> block.
        $this->site->update(['settings' => [
            'palette_overrides' => ['primary' => '#fff; } body { display: none'],
        ]]);

        $css = $this->theme()->cssVariables();

        $this->assertStringNotContainsString('}', str_replace(':root{', '', substr($css, 0, -1)));
        $this->assertStringContainsString('--site-primary: #005a9e;', $css);
    }

    public function test_preview_is_honoured_for_a_site_admin_and_ignored_for_everyone_else(): void
    {
        $this->get('/?preview_template=bold')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('template', 'academic'));

        $this->actingAs(User::factory()->create())
            ->get('/?preview_template=bold')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('template', 'academic'));

        $this->actingAs($this->admin)
            ->get('/?preview_template=bold')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('template', 'bold'));
    }

    public function test_previewing_does_not_persist_the_choice(): void
    {
        $this->actingAs($this->admin)->get('/?preview_template=bold');

        $this->assertArrayNotHasKey('template', $this->site->fresh()->settings ?? []);
    }

    public function test_a_non_admin_cannot_view_or_change_appearance(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/cms/appearance')->assertForbidden();
        $this->actingAs($user)
            ->put('/cms/appearance', ['template' => 'bold', 'font' => 'academic'])
            ->assertForbidden();

        $this->assertArrayNotHasKey('template', $this->site->fresh()->settings ?? []);
    }

    public function test_the_appearance_screen_offers_every_template(): void
    {
        $this->actingAs($this->admin)
            ->get('/cms/appearance')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('cms/appearance')
                ->where('current', 'academic')
                ->where('currentFont', 'academic')
                ->has('templates', count(SiteTheme::TEMPLATES))
                ->has('fonts', count(SiteTheme::FONTS))
                ->has('palette')
                ->has('overrides'));
    }

    public function test_saving_appearance_leaves_other_settings_alone(): void
    {
        $this->site->update(['settings' => [
            'tagline' => 'Original tagline',
            'menu_items' => [['label' => 'Ethics', 'url' => '/pages/ethics']],
        ]]);

        $this->actingAs($this->admin)->put('/cms/appearance', ['template' => 'minimal', 'font' => 'modern']);

        $settings = $this->site->fresh()->settings;
        $this->assertSame('Original tagline', $settings['tagline']);
        $this->assertSame('Ethics', $settings['menu_items'][0]['label']);
        $this->assertSame('minimal', $settings['template']);
        $this->assertSame('modern', $settings['font']);
    }

    /**
     * A font picker is the obvious way to reintroduce the Khmer rendering bug that named font
     * stacks were added to fix, so the Khmer half of every stack is not the admin's to remove.
     */
    public function test_every_font_pairing_keeps_the_khmer_faces(): void
    {
        foreach (array_keys(SiteTheme::FONTS) as $key) {
            $this->site->update(['settings' => ['font' => $key]]);

            $css = $this->theme()->cssVariables();

            // Named --site-font-*: --font-* is declared by the compiled stylesheet, which loads
            // after the head and would override whatever is written here.
            $this->assertStringContainsString('--site-font-display:', $css);
            $this->assertStringNotContainsString('--font-display:', $css);
            $this->assertStringContainsString('Kantumruy Pro', $css, "Font pairing {$key} dropped Kantumruy Pro.");
            $this->assertStringContainsString('Noto Sans Khmer', $css, "Font pairing {$key} dropped Noto Sans Khmer.");
            $this->assertStringContainsString('Noto+Sans+Khmer', $this->theme()->fontHref());
        }
    }

    public function test_an_unknown_font_falls_back(): void
    {
        $this->site->update(['settings' => ['font' => 'comic-sans']]);

        $this->assertSame(SiteTheme::DEFAULT_FONT, $this->theme()->fontKey());
    }

    public function test_every_template_defines_a_complete_palette(): void
    {
        // A template that only overrode some tokens would inherit the rest from whichever
        // template happened to render before it.
        $tokens = array_keys(SiteTheme::TEMPLATES[SiteTheme::DEFAULT_TEMPLATE]['palette']);

        foreach (SiteTheme::TEMPLATES as $key => $template) {
            $this->assertSame($tokens, array_keys($template['palette']), "Template {$key} has an incomplete palette.");

            foreach ($template['palette'] as $token => $value) {
                $this->assertTrue(SiteTheme::isHex($value), "Template {$key} token {$token} is not a hex colour.");
            }
        }
    }
}
