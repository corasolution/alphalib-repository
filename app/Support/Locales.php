<?php

namespace Alpha\Journal\Support;

use Alpha\Journal\Models\Site;

/**
 * Which languages this installation publishes in, and which one the current visitor is reading.
 *
 * The list comes from Site.installed_locales rather than a constant, because it is already the
 * column the rest of the app treats as authoritative (Journal::$installed_locales mirrors it per
 * journal, and MultilingualText's fallback chain is built from it).
 */
class Locales
{
    /** Display names, written in each language rather than in English. */
    public const NAMES = [
        'en' => 'English',
        'km' => 'ភាសាខ្មែរ',
    ];

    /** Short labels for the header switch. */
    public const SHORT = [
        'en' => 'EN',
        'km' => 'KH',
    ];

    /**
     * @return list<string> the locales this site publishes in, always including the primary one
     */
    public function available(): array
    {
        $site = Site::first();

        if (! $site) {
            return [config('app.locale')];
        }

        $installed = array_values(array_filter(
            $site->installed_locales ?? [],
            fn ($locale) => isset(self::NAMES[$locale]),
        ));

        // The primary locale is always offered even if someone removed it from the list, or the
        // site would advertise no language at all and the switch would render empty.
        return $installed !== [] ? $installed : [$site->primary_locale ?? config('app.locale')];
    }

    public function isAvailable(string $locale): bool
    {
        return in_array($locale, $this->available(), true);
    }

    /** The locale in effect for this request. */
    public function current(): string
    {
        return app()->getLocale();
    }

    /**
     * The fallback chain to hand MultilingualText::get().
     *
     * Reader's choice first, then the site's primary language, then English — so a record that
     * has no Khmer translation shows its English text rather than a blank space. Duplicates are
     * stripped because get() walks the chain in order and repeats cost a lookup for nothing.
     *
     * @return list<string>
     */
    public function chain(?string $preferred = null): array
    {
        $site = Site::first();

        return array_values(array_unique(array_filter([
            $preferred ?? $this->current(),
            $site?->primary_locale,
            config('app.fallback_locale'),
        ])));
    }

    /** @return array<int, array{code: string, short: string, name: string}> for the UI switch */
    public function forSwitcher(): array
    {
        return array_map(
            fn (string $locale) => [
                'code' => $locale,
                'short' => self::SHORT[$locale] ?? strtoupper($locale),
                'name' => self::NAMES[$locale] ?? $locale,
            ],
            $this->available(),
        );
    }
}
