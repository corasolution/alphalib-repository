<?php

namespace Alpha\Journal\Http\Middleware;

use Alpha\Journal\Models\SiteBadge;
use Alpha\Journal\Support\CurrentSite;
use Alpha\Journal\Support\Locales;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            // The system's own name and logo, admin-editable in CMS → Homepage. Shared rather
            // than hardcoded in the components so the sidebar and the login screen show the
            // customer's identity instead of the starter kit's.
            'name' => app(CurrentSite::class)->name(),
            'site' => [
                'name' => app(CurrentSite::class)->name(),
                'logoUrl' => app(CurrentSite::class)->logoUrl(),
            ],
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],

            // The "Indexed by" / "Tools" strips. Shared rather than passed per-page because they
            // render in the public layout, which six different controllers feed — a page that
            // forgot the prop would silently drop the strips rather than fail visibly.
            'siteBadges' => $this->badges(),

            // Language state, shared rather than passed per-page: the switch lives in the layout,
            // so every page needs it, and a page that forgot to pass it would render an empty
            // switcher rather than an obvious error.
            'locale' => app()->getLocale(),
            'locales' => app(Locales::class)->forSwitcher(),

            // The public-site interface strings for the active locale, merged over English so a
            // missing Khmer line falls back to readable English rather than showing its key.
            'translations' => $this->translations(),
        ]);
    }

    /**
     * The site strings for the active locale, layered over English.
     *
     * array_replace_recursive rather than a plain merge: the file is nested by section, and a
     * partial Khmer translation must fall back per line, not lose the whole section its one
     * translated string happens to sit in.
     *
     * @return array<string, mixed>
     */
    private function translations(): array
    {
        $english = (array) trans('site', [], 'en');
        $current = (array) trans('site', [], app()->getLocale());

        return array_replace_recursive($english, is_array($current) ? $current : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function badges(): array
    {
        try {
            return SiteBadge::query()
                ->orderBy('group')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (SiteBadge $badge) => [
                    'group' => $badge->group,
                    'label' => $badge->label,
                    'url' => $badge->url,
                    'logoUrl' => $badge->logoUrl(),
                ])
                ->groupBy('group')
                ->toArray();
        } catch (\Throwable) {
            // Runs on every request, including ones served before the table exists (a fresh
            // checkout mid-migrate). A missing trust strip is not worth a 500.
            return [];
        }
    }
}
