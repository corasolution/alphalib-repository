<?php

namespace Alpha\Journal\Http\Middleware;

use Alpha\Journal\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the reader's chosen language to the request.
 *
 * Session-backed rather than URL-prefixed (/km/pages/ethics). A prefix would be better for SEO
 * and shareable links, but it would change every existing public URL — including the menu links
 * an admin has already saved and anything indexers have already harvested. Worth revisiting as a
 * deliberate migration; not worth breaking those silently as a side effect of adding a switch.
 *
 * The stored value is re-validated on every request, not just when it is set: a locale can be
 * removed from Site.installed_locales while somebody still has the old one in their session, and
 * an unvalidated read would then serve a language the site no longer publishes.
 */
class SetLocale
{
    public function __construct(private readonly Locales $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        $chosen = $request->session()->get('locale');

        if (is_string($chosen) && $this->locales->isAvailable($chosen)) {
            app()->setLocale($chosen);
        }

        return $next($request);
    }
}
