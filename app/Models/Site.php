<?php

namespace Alpha\Journal\Models;

use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class Site extends Model
{
    /**
     * The social platforms the public footer can render an icon for.
     *
     * One list, because it governs two things that must agree: which links the CMS will save, and
     * which the public site will display. Held separately (it was duplicated in two controllers)
     * they can drift, and the failure is silent — an admin saves a link that simply never appears
     * anywhere, with no error to explain why.
     *
     * Keep in step with SOCIAL_ICONS in resources/js/layouts/public-site-layout.tsx.
     */
    public const SOCIAL_PLATFORMS = ['facebook', 'twitter', 'linkedin', 'youtube', 'instagram'];

    /**
     * How many highlight cards the homepage strip holds. Fixed rather than open-ended because the
     * design is a three-across row — a fourth card has nowhere to go without redesigning it.
     */
    public const HIGHLIGHT_SLOTS = 3;

    protected $fillable = [
        'title', 'primary_locale', 'installed_locales', 'redirect_journal_id', 'settings',
        'logo_disk', 'logo_path', 'logo_original_name',
        'ai_credentials', 'ai_enabled', 'integration_credentials', 'storage_credentials',
    ];

    protected function casts(): array
    {
        return [
            'installed_locales' => 'array',
            'settings' => 'array',
            // Billable provider keys. `encrypted:array` so the whole provider => key map is
            // sealed at rest — same treatment users.orcid_access_token already gets, and the
            // reason APP_KEY must be backed up alongside the database.
            'ai_credentials' => 'encrypted:array',
            'integration_credentials' => 'encrypted:array',
            'storage_credentials' => 'encrypted:array',
            'ai_enabled' => 'boolean',
        ];
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }

    public function heroSlides(): HasMany
    {
        return $this->hasMany(HeroSlide::class)->orderBy('sort_order');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(SitePage::class);
    }

    /**
     * The homepage highlight cards, keeping only slots an admin has actually filled in.
     *
     * These are stored content, not hardcoded copy, for the same reason everything else on the
     * public site is: a card strip baked into the template is marketing this app invented on the
     * customer's behalf, and it cannot be corrected by the people whose journal it describes.
     *
     * @return list<array{title: ?string, body: ?string}>
     */
    public function highlights(): array
    {
        $translated = $this->translated('highlights') ?? [];

        return collect($this->settings['highlights'] ?? [])
            ->take(self::HIGHLIGHT_SLOTS)
            ->map(fn ($card, $index) => [
                'title' => $this->pick($translated[$index]['title'] ?? null, $card['title'] ?? null),
                'body' => $this->pick($translated[$index]['body'] ?? null, $card['body'] ?? null),
            ])
            // A card with neither a title nor body would render as an empty numbered box.
            ->filter(fn (array $card) => $card['title'] !== null || $card['body'] !== null)
            ->values()
            ->all();
    }

    /**
     * The configured social links, filtered to platforms the footer can actually render.
     *
     * Every public page needs these — the site chrome must look identical wherever a visitor
     * lands — so it lives here rather than being rebuilt in each controller.
     *
     * @return Collection<string, string>
     */
    /**
     * A translated value for the reader's language, or null when the site is being read in its
     * primary language or no translation was entered. Callers fall back to the primary text, so
     * an untranslated site simply keeps showing what it showed before.
     */
    public function translated(string $key): mixed
    {
        $locale = app()->getLocale();

        if ($locale === $this->primary_locale) {
            return null;
        }

        $value = $this->settings['translations'][$locale][$key] ?? null;

        return filled($value) ? $value : null;
    }

    public function displayTitle(): string
    {
        return $this->translated('title') ?? $this->title;
    }

    public function displayTagline(): ?string
    {
        return $this->translated('tagline') ?? ($this->settings['tagline'] ?? null);
    }

    public function displayAbout(): ?string
    {
        return $this->translated('about') ?? ($this->settings['about'] ?? null);
    }

    public function displayFooterText(): ?string
    {
        return $this->translated('footer_text') ?? ($this->settings['footer_text'] ?? null);
    }

    /**
     * The header menu with labels in the reader's language. Translations are keyed by URL rather
     * than position, so reordering or inserting an item cannot attach a label to the wrong link.
     *
     * @return array<int, array{label: string, url: string}>
     */
    public function displayMenuItems(): array
    {
        $labels = $this->translated('menu_labels') ?? [];

        return collect($this->settings['menu_items'] ?? [])
            ->map(fn (array $item) => [
                'label' => filled($labels[$item['url']] ?? null) ? $labels[$item['url']] : $item['label'],
                'url' => $item['url'],
            ])
            ->all();
    }

    private function pick(mixed $translated, mixed $base): ?string
    {
        $value = filled($translated) ? $translated : $base;

        return filled($value) ? trim($value) : null;
    }

    public function socialLinks(): Collection
    {
        return collect($this->settings['social_links'] ?? [])
            ->only(self::SOCIAL_PLATFORMS)
            ->filter(fn ($url) => filled($url));
    }

    public function redirectJournal(): ?Journal
    {
        return $this->redirect_journal_id ? Journal::find($this->redirect_journal_id) : null;
    }

    public function storeLogo(UploadedFile $file): void
    {
        $stored = app(FileStorage::class)->store($file, 'site');

        $this->update([
            'logo_disk' => $stored->disk,
            'logo_path' => $stored->path,
            'logo_original_name' => $stored->originalName,
        ]);
    }

    public function logoUrl(): ?string
    {
        if ($this->logo_path === null) {
            return null;
        }

        return app(FileStorage::class)->temporaryUrl($this->logo_path, now()->addDay(), $this->logo_disk);
    }
}
