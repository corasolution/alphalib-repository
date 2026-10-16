<?php

namespace Alpha\Journal\Support;

use Illuminate\Http\Request;

/**
 * Which landing-page template the public site uses, and the palette and type that go with it.
 *
 * Templates differ in layout AND colour, but every one renders the same CMS content — title,
 * logo, tagline, about, highlight cards, hero slides, journals. Choosing a design never changes
 * what the site says, only how it looks.
 */
class SiteTheme
{
    public const DEFAULT_TEMPLATE = 'academic';

    /**
     * The built-in templates. Each palette is a complete set: a template that only overrode some
     * tokens would inherit the rest from whichever template happened to be active before.
     *
     * @var array<string, array{name: string, description: string, palette: array<string, string>}>
     */
    public const TEMPLATES = [
        'academic' => [
            'name' => 'Academic',
            'description' => 'Hero slideshow, numbered highlights, journal cards. The original design.',
            'palette' => [
                'primary' => '#005a9e',
                'primary-dark' => '#004277',
                'primary-deep' => '#00446e',
                'accent' => '#a2c9ff',
                'surface' => '#eff6ff',
                'surface-alt' => '#cfe2f5',
                'border' => '#c1c7d2',
                'ink' => '#121c2a',
                'body' => '#374151',
                'muted' => '#727782',
            ],
        ],
        'classic' => [
            'name' => 'Classic',
            'description' => 'Serif masthead and ruled sections. Reads like a printed journal.',
            'palette' => [
                'primary' => '#7a2e2e',
                'primary-dark' => '#5c1f1f',
                'primary-deep' => '#4a1919',
                'accent' => '#e8c9a0',
                'surface' => '#faf6f0',
                'surface-alt' => '#f0e6d8',
                'border' => '#d9cfc2',
                'ink' => '#241a14',
                'body' => '#3d332c',
                'muted' => '#7a6f66',
            ],
        ],
        'magazine' => [
            'name' => 'Magazine',
            'description' => 'Featured block and an image-led grid. Denser, more editorial.',
            'palette' => [
                'primary' => '#0f7b6c',
                'primary-dark' => '#0a5a4f',
                'primary-deep' => '#084a41',
                'accent' => '#8fe3d2',
                'surface' => '#f0faf7',
                'surface-alt' => '#d7f0e9',
                'border' => '#c2d8d2',
                'ink' => '#0f2521',
                'body' => '#33443f',
                'muted' => '#6b7c77',
            ],
        ],
        'minimal' => [
            'name' => 'Minimal',
            'description' => 'No hero imagery. Large type and generous whitespace, content first.',
            'palette' => [
                'primary' => '#1f1f1f',
                'primary-dark' => '#111111',
                'primary-deep' => '#000000',
                'accent' => '#c9c9c9',
                'surface' => '#f6f6f6',
                'surface-alt' => '#ececec',
                'border' => '#d8d8d8',
                'ink' => '#111111',
                'body' => '#3a3a3a',
                'muted' => '#767676',
            ],
        ],
        'blue' => [
            'name' => 'Blue',
            'description' => 'Deep navy hero with a warm gold glow, elevated white cards and soft blue surfaces. Modelled on the Rule Library catalogue.',
            'palette' => [
                'primary' => '#0b4b8f',
                'primary-dark' => '#083a70',
                'primary-deep' => '#062a52',
                'accent' => '#a9cdf0',
                'surface' => '#f6f7fa',
                'surface-alt' => '#d9e7f5',
                'border' => '#dde1ea',
                'ink' => '#14213d',
                'body' => '#43506b',
                'muted' => '#6b7691',
            ],
        ],
        'bold' => [
            'name' => 'Bold',
            'description' => 'Full-bleed colour blocks and oversized headings. High contrast.',
            'palette' => [
                'primary' => '#5b21b6',
                'primary-dark' => '#3f1580',
                'primary-deep' => '#2e0f5f',
                'accent' => '#fbbf24',
                'surface' => '#f5f1ff',
                'surface-alt' => '#e6dcff',
                'border' => '#cfc4ea',
                'ink' => '#1c1230',
                'body' => '#3b3550',
                'muted' => '#736c8a',
            ],
        ],
    ];

    /**
     * Font pairings. Every stack keeps Kantumruy Pro and Noto Sans Khmer behind the Latin faces —
     * a font picker is the obvious way to reintroduce the Khmer rendering bug those fallbacks were
     * added to fix, so the Khmer half is not the admin's to remove.
     *
     * @var array<string, array{name: string, display: string, body: string, google: string}>
     */
    public const FONTS = [
        'academic' => [
            'name' => 'Hanken Grotesk + Source Serif',
            'display' => "'Hanken Grotesk'",
            'body' => "'Source Serif 4'",
            'google' => 'Hanken+Grotesk:ital,wght@0,400..800;1,400..800&family=Source+Serif+4:opsz,wght@8..60,400..600',
        ],
        'serif' => [
            'name' => 'Playfair Display + Lora',
            'display' => "'Playfair Display'",
            'body' => 'Lora',
            'google' => 'Playfair+Display:wght@500;600;700&family=Lora:ital,wght@0,400..600;1,400..600',
        ],
        'modern' => [
            'name' => 'Inter + Inter',
            'display' => 'Inter',
            'body' => 'Inter',
            'google' => 'Inter:wght@400;500;600;700;800',
        ],
    ];

    public const DEFAULT_FONT = 'academic';

    public function __construct(private readonly CurrentSite $site) {}

    /**
     * The active template key.
     *
     * A site admin may preview another template with ?preview_template=. Restricted to admins
     * because it changes what every visitor's cache and any screenshot would show otherwise, and
     * because choosing a design from a thumbnail alone is guessing.
     */
    public function template(?Request $request = null): string
    {
        $preview = $request?->query('preview_template');

        if (is_string($preview) && isset(self::TEMPLATES[$preview]) && $request?->user()?->is_site_admin) {
            return $preview;
        }

        $stored = $this->settings()['template'] ?? null;

        // Falls back rather than rendering nothing if a key is ever removed or mistyped.
        return isset(self::TEMPLATES[$stored]) ? $stored : self::DEFAULT_TEMPLATE;
    }

    /**
     * The palette actually applied: the template's defaults with any admin overrides layered on.
     *
     * Overrides are stored separately from the template so switching design keeps a university's
     * brand colours instead of silently discarding them.
     *
     * @return array<string, string>
     */
    public function palette(?Request $request = null): array
    {
        $base = self::TEMPLATES[$this->template($request)]['palette'];
        $overrides = $this->settings()['palette_overrides'] ?? [];

        if (! is_array($overrides)) {
            return $base;
        }

        foreach ($overrides as $token => $value) {
            if (isset($base[$token]) && self::isHex($value)) {
                $base[$token] = strtolower($value);
            }
        }

        return $base;
    }

    /** @return array{name: string, display: string, body: string, google: string} */
    public function font(): array
    {
        $stored = $this->settings()['font'] ?? null;

        return self::FONTS[$stored] ?? self::FONTS[self::DEFAULT_FONT];
    }

    public function fontKey(): string
    {
        $stored = $this->settings()['font'] ?? null;

        return isset(self::FONTS[$stored]) ? $stored : self::DEFAULT_FONT;
    }

    /**
     * The palette as a `:root` declaration for the document head.
     *
     * Every value is re-validated as hex on the way out. This string lands inside a <style> block,
     * so an unvalidated value would be a CSS injection — `red; } body { display:none } :root {`
     * is a defacement, and worse is available.
     */
    public function cssVariables(?Request $request = null): string
    {
        $declarations = [];

        foreach ($this->palette($request) as $token => $value) {
            if (self::isHex($value)) {
                $declarations[] = "--site-{$token}: {$value};";
            }
        }

        $font = $this->font();
        $khmer = "'Kantumruy Pro', 'Noto Sans Khmer'";

        // --site-font-*, not --font-*. Tailwind's @theme compiles --font-display into the
        // stylesheet's own :root, which loads after this block and would otherwise win —
        // the admin's font choice applied on some loads and not others. app.css reads these
        // through var(), so the head wins and the built-in stack stays the fallback.
        $declarations[] = "--site-font-display: {$font['display']}, {$khmer}, ui-sans-serif, system-ui, sans-serif;";
        $declarations[] = "--site-font-body: {$font['body']}, {$khmer}, ui-serif, Georgia, serif;";

        return ':root{'.implode('', $declarations).'}';
    }

    /** The Google Fonts href for the active pairing, with the Khmer faces always included. */
    public function fontHref(): string
    {
        return 'https://fonts.googleapis.com/css2?family='.$this->font()['google']
            .'&family=Kantumruy+Pro:wght@400;500;600;700'
            .'&family=Noto+Sans+Khmer:wght@400;500;600;700&display=swap';
    }

    /** @return array<int, array{key: string, name: string, description: string, palette: array<string, string>}> */
    public static function forPicker(): array
    {
        return array_values(array_map(
            fn (string $key) => ['key' => $key] + self::TEMPLATES[$key],
            array_keys(self::TEMPLATES),
        ));
    }

    public static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        $settings = $this->site->get()?->settings;

        return is_array($settings) ? $settings : [];
    }
}
