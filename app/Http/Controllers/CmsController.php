<?php

namespace Alpha\Journal\Http\Controllers;

use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\SiteBadge;
use Alpha\Journal\Support\SiteTheme;
use Alpha\Journal\Support\UploadLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landing-page content management, split into focused screens (homepage, menu, footer, hero
 * slides, pages) reachable from the CMS group in the sidebar. Every method is gated by
 * SitePolicy::manage — the one global is_site_admin role (CLAUDE.md §4).
 *
 * Because each screen posts only its own fields, every write goes through mergeSettings():
 * writing Site.settings wholesale would mean saving the menu silently wipes the tagline, about
 * text, footer and social links.
 */
class CmsController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('cms.homepage.edit');
    }

    public function homepage(): Response
    {
        $site = Site::firstOrFail();

        Gate::authorize('manage', Site::class);

        return Inertia::render('cms/homepage', [
            'site' => [
                'title' => $site->title,
                'tagline' => $site->settings['tagline'] ?? '',
                'about' => $site->settings['about'] ?? '',
                'redirectJournalId' => $site->redirect_journal_id,
                'logoUrl' => $site->logoUrl(),
            ],
            // Padded to a fixed length so the form always shows every slot — an admin filling in
            // the third card shouldn't have to first discover a hidden "add another" control.
            'highlights' => collect($site->settings['highlights'] ?? [])
                ->pad(Site::HIGHLIGHT_SLOTS, [])
                ->take(Site::HIGHLIGHT_SLOTS)
                ->map(fn ($card) => [
                    'title' => $card['title'] ?? '',
                    'body' => $card['body'] ?? '',
                ])
                ->values(),
            'journals' => Journal::where('is_enabled', true)
                ->get()
                ->map(fn ($journal) => ['id' => $journal->id, 'title' => $journal->title->get($site->primary_locale, 'en') ?? $journal->path]),
        ]);
    }

    public function updateHomepage(Request $request): RedirectResponse
    {
        $site = Site::firstOrFail();

        Gate::authorize('manage', Site::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:500'],
            'about' => ['nullable', 'string', 'max:5000'],
            'highlights' => ['nullable', 'array', 'max:'.Site::HIGHLIGHT_SLOTS],
            'highlights.*.title' => ['nullable', 'string', 'max:120'],
            'highlights.*.body' => ['nullable', 'string', 'max:600'],
            'redirect_journal_id' => ['nullable', 'integer', 'exists:journals,id'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:'.UploadLimits::imageKilobytes()],
        ]);

        if ($request->hasFile('logo')) {
            $site->storeLogo($request->file('logo'));
        }

        $site->update([
            'title' => $data['title'],
            'redirect_journal_id' => $data['redirect_journal_id'] ?? null,
        ]);

        $this->mergeSettings($site, [
            'tagline' => $data['tagline'] ?? null,
            'about' => $data['about'] ?? null,
            // Blank slots are dropped rather than stored, so the public strip never renders an
            // empty numbered card. Clearing all three leaves an empty array, which mergeSettings
            // then filters out entirely — that is how an admin turns the strip off.
            'highlights' => collect($data['highlights'] ?? [])
                ->map(fn ($card) => [
                    'title' => filled($card['title'] ?? null) ? trim($card['title']) : null,
                    'body' => filled($card['body'] ?? null) ? trim($card['body']) : null,
                ])
                ->filter(fn ($card) => $card['title'] !== null || $card['body'] !== null)
                ->values()
                ->all(),
        ]);

        return back()->with('success', 'Homepage updated.');
    }

    public function appearance(SiteTheme $theme): Response
    {
        $site = Site::firstOrFail();

        Gate::authorize('manage', Site::class);

        return Inertia::render('cms/appearance', [
            'templates' => SiteTheme::forPicker(),
            'current' => $theme->template(),
            'fonts' => collect(SiteTheme::FONTS)->map(fn ($font, $key) => ['key' => $key, 'name' => $font['name']])->values(),
            'currentFont' => $theme->fontKey(),
            // The palette actually in force, so the colour inputs open showing what is on screen
            // rather than empty boxes the admin has to guess the current value for.
            'palette' => $theme->palette(),
            'overrides' => $site->settings['palette_overrides'] ?? [],
        ]);
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        $site = Site::firstOrFail();

        Gate::authorize('manage', Site::class);

        $data = $request->validate([
            'template' => ['required', Rule::in(array_keys(SiteTheme::TEMPLATES))],
            'font' => ['required', Rule::in(array_keys(SiteTheme::FONTS))],
            'palette_overrides' => ['nullable', 'array'],
            // Strict hex. These values are written into a :root block in the document head, so an
            // unvalidated one is a CSS injection — "red;} body{display:none}" is a defacement.
            'palette_overrides.*' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'palette_overrides.*.regex' => 'Colours must be a six-digit hex value such as #005a9e.',
        ]);

        $overrides = collect($data['palette_overrides'] ?? [])
            // Only tokens the palette actually defines, so a stray key cannot accumulate in
            // settings and never be read.
            ->only(array_keys(SiteTheme::TEMPLATES[SiteTheme::DEFAULT_TEMPLATE]['palette']))
            ->filter(fn ($value) => SiteTheme::isHex($value))
            ->map(fn ($value) => strtolower($value))
            ->all();

        $this->mergeSettings($site, [
            'template' => $data['template'],
            'font' => $data['font'],
            'palette_overrides' => $overrides ?: null,
        ]);

        return back()->with('success', 'Appearance updated.');
    }

    public function menu(): Response
    {
        $site = Site::firstOrFail();

        Gate::authorize('manage', Site::class);

        return Inertia::render('cms/menu', [
            'menuItems' => $site->settings['menu_items'] ?? [],
        ]);
    }

    public function updateMenu(Request $request): RedirectResponse
    {
        $site = Site::firstOrFail();

        Gate::authorize('manage', Site::class);

        $data = $request->validate([
            'menu_items' => ['nullable', 'array'],
            'menu_items.*.label' => ['required_with:menu_items', 'string', 'max:100'],
            'menu_items.*.url' => ['required_with:menu_items', 'string', 'max:500'],
        ]);

        $this->mergeSettings($site, [
            'menu_items' => array_values($data['menu_items'] ?? []) ?: null,
        ]);

        return back()->with('success', 'Menu updated.');
    }

    public function footer(): Response
    {
        $site = Site::firstOrFail();

        Gate::authorize('manage', Site::class);

        return Inertia::render('cms/footer', [
            'footerText' => $site->settings['footer_text'] ?? '',
            'socialLinks' => collect(Site::SOCIAL_PLATFORMS)
                ->mapWithKeys(fn ($platform) => [$platform => $site->settings['social_links'][$platform] ?? '']),
        ]);
    }

    public function updateFooter(Request $request): RedirectResponse
    {
        $site = Site::firstOrFail();

        Gate::authorize('manage', Site::class);

        $data = $request->validate([
            'footer_text' => ['nullable', 'string', 'max:1000'],
            'social_links' => ['nullable', 'array'],
            'social_links.*' => ['nullable', 'string', 'max:500'],
        ]);

        $this->mergeSettings($site, [
            'footer_text' => $data['footer_text'] ?? null,
            'social_links' => array_filter(
                collect($data['social_links'] ?? [])->only(Site::SOCIAL_PLATFORMS)->all(),
                fn ($value) => filled($value),
            ) ?: null,
        ]);

        return back()->with('success', 'Footer updated.');
    }

    /**
     * The "Indexed by" and "Tools" strips.
     *
     * Nothing is pre-filled. "Indexed by X" tells authors where their work will be discoverable —
     * a claim only someone who knows it is true should be making — so this screen starts empty
     * and stays empty until an admin adds an entry.
     */
    public function badges(): Response
    {
        $site = Site::firstOrFail();

        Gate::authorize('manage', Site::class);

        return Inertia::render('cms/badges', [
            'groups' => SiteBadge::GROUPS,
            'badges' => SiteBadge::where('site_id', $site->id)
                ->orderBy('group')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (SiteBadge $badge) => [
                    'id' => $badge->id,
                    'group' => $badge->group,
                    'label' => $badge->label,
                    'url' => $badge->url,
                    'logoUrl' => $badge->logoUrl(),
                ])
                ->groupBy('group'),
            // Names and official links, offered as a starting point. Adding one is still a
            // deliberate act — this is a convenience, not a default.
            'suggestions' => [
                SiteBadge::GROUP_INDEXED_BY => [
                    ['label' => 'OCLC WorldCat', 'url' => 'https://search.worldcat.org'],
                    ['label' => 'Google Scholar', 'url' => 'https://scholar.google.com'],
                    ['label' => 'ISSN', 'url' => 'https://portal.issn.org'],
                    ['label' => 'Crossref', 'url' => 'https://www.crossref.org'],
                    ['label' => 'DOI', 'url' => 'https://www.doi.org'],
                    ['label' => 'DOAJ', 'url' => 'https://doaj.org'],
                    ['label' => 'ORCID', 'url' => 'https://orcid.org'],
                ],
                SiteBadge::GROUP_TOOLS => [
                    ['label' => 'Grammarly', 'url' => 'https://www.grammarly.com'],
                    ['label' => 'Mendeley', 'url' => 'https://www.mendeley.com'],
                    ['label' => 'Turnitin', 'url' => 'https://www.turnitin.com'],
                    ['label' => 'EndNote', 'url' => 'https://endnote.com'],
                    ['label' => 'Zotero', 'url' => 'https://www.zotero.org'],
                ],
            ],
        ]);
    }

    public function storeBadge(Request $request): RedirectResponse
    {
        $site = Site::firstOrFail();

        Gate::authorize('manage', Site::class);

        $data = $request->validate([
            'group' => ['required', Rule::in(array_keys(SiteBadge::GROUPS))],
            'label' => ['required', 'string', 'max:60'],
            'url' => ['nullable', 'string', 'max:500', 'url'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:'.UploadLimits::imageKilobytes()],
        ]);

        SiteBadge::storeFor($site, $data['group'], $data['label'], $data['url'] ?? null, $request->file('logo'));

        return back()->with('success', "{$data['label']} added.");
    }

    public function updateBadge(Request $request, SiteBadge $badge): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'url' => ['nullable', 'string', 'max:500', 'url'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:'.UploadLimits::imageKilobytes()],
        ]);

        $badge->update(['label' => $data['label'], 'url' => $data['url'] ?? null]);

        if ($request->hasFile('logo')) {
            $badge->replaceLogo($request->file('logo'));
        }

        return back()->with('success', "{$badge->label} updated.");
    }

    public function destroyBadge(SiteBadge $badge): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $label = $badge->label;
        $badge->delete();

        return back()->with('success', "{$label} removed.");
    }

    public function heroSlides(): Response
    {
        $site = Site::firstOrFail();

        Gate::authorize('manage', Site::class);

        return Inertia::render('cms/hero-slides', [
            'heroSlides' => $site->heroSlides->map(fn ($slide) => [
                'id' => $slide->id,
                'url' => $slide->url(),
                'caption' => $slide->caption,
            ]),
        ]);
    }

    /**
     * Applies only the keys this screen owns, leaving every other setting untouched. A null (or
     * blank) value still clears its own key — merge-safety must not mean a field can never be
     * emptied once set.
     *
     * @param  array<string, mixed>  $changes
     */
    private function mergeSettings(Site $site, array $changes): void
    {
        $merged = array_merge($site->settings ?? [], $changes);

        $site->update([
            'settings' => array_filter($merged, fn ($value) => filled($value)),
        ]);
    }
}
