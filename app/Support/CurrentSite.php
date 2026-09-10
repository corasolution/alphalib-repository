<?php

namespace Alpha\Journal\Support;

use Alpha\Journal\Models\Site;

/**
 * The one Site row, fetched at most once per request.
 *
 * There is exactly one Site per installation (CLAUDE.md §2), and it is now read on nearly every
 * request — the Inertia share needs its name and logo for the chrome, Locales needs its installed
 * locales, and the blade root view needs its name for the tab title. Without memoisation that is
 * three queries for one row that cannot change mid-request.
 *
 * Bound as a singleton, so the cache lives exactly as long as the request does. A test that
 * creates a Site after something has already asked for one should call forget().
 */
class CurrentSite
{
    private ?Site $site = null;

    private bool $loaded = false;

    public function get(): ?Site
    {
        if (! $this->loaded) {
            // Wrapped: this runs during the Inertia share on every request, including ones served
            // before the first migration, where the table does not exist yet.
            try {
                $this->site = Site::first();
            } catch (\Throwable) {
                $this->site = null;
            }

            $this->loaded = true;
        }

        return $this->site;
    }

    /** The system name shown in the sidebar, on the login screen, and in the browser tab. */
    public function name(): string
    {
        return $this->get()?->title ?: config('app.name', 'Alpha Journal');
    }

    public function logoUrl(): ?string
    {
        return $this->get()?->logoUrl();
    }

    /** Drops the cache — needed when a request changes the row it already read. */
    public function forget(): void
    {
        $this->site = null;
        $this->loaded = false;
    }
}
