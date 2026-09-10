<?php

namespace Alpha\Journal\Http\Controllers;

use Alpha\Journal\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Switches the reader's language and returns them to the page they were on.
     *
     * POST rather than GET: it changes state that persists across requests, so it must not be
     * something a crawler can trigger by following a link, or a prefetch can flip mid-visit.
     */
    public function __invoke(Request $request, Locales $locales): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'string', 'max:10']]);

        // An unavailable locale is ignored rather than rejected — a stale switch on a cached page
        // shouldn't greet a reader with a validation error; it should just leave them as they are.
        if ($locales->isAvailable($data['locale'])) {
            $request->session()->put('locale', $data['locale']);
        }

        return back();
    }
}
