<?php

namespace Alpha\Journal\Http\Controllers;

use Alpha\Journal\Models\HeroSlide;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Support\UploadLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The site homepage hero slideshow — a separate small controller from SiteController because
 * slides are a list of individually-addressable resources (upload one, delete one), unlike the
 * single-value logo/tagline/footer fields the main settings form saves as a batch.
 */
class HeroSlideController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $site = Site::firstOrFail();

        Gate::authorize('manage', Site::class);

        $data = $request->validate([
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:'.UploadLimits::imageKilobytes()],
            'caption' => ['nullable', 'string', 'max:200'],
        ]);

        HeroSlide::storeFor($site, $request->file('image'), $data['caption'] ?? null);

        return back()->with('success', 'Slide added.');
    }

    public function destroy(HeroSlide $heroSlide): RedirectResponse
    {
        Gate::authorize('manage', Site::class);

        $heroSlide->delete();

        return back()->with('success', 'Slide removed.');
    }
}
