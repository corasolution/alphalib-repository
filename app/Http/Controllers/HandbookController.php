<?php

namespace Alpha\Journal\Http\Controllers;

use Alpha\Journal\Models\Site;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform handbook — how to submit, review, edit, publish and administer.
 *
 * Public and unauthenticated on purpose. Prospective authors need to read what submitting
 * involves *before* they have an account, and external reviewers need the anonymity rules and
 * the recommendation vocabulary without being asked to log in first. Putting it behind the staff
 * login would hide it from most of the people it is written for.
 *
 * The content lives in the page component rather than in a CMS page because a plain-text body
 * (max 20,000 characters, rendered as paragraphs) cannot hold the eight reference tables or the
 * workflow diagram, which are the parts editors actually come back for.
 */
class HandbookController extends Controller
{
    public function __invoke(): Response
    {
        $site = Site::firstOrFail();

        return Inertia::render('public/handbook', [
            'site' => [
                'title' => $site->displayTitle(),
                'logoUrl' => $site->logoUrl(),
                'footerText' => $site->displayFooterText(),
            ],
            'menuItems' => $site->displayMenuItems(),
            'socialLinks' => $site->socialLinks(),
        ]);
    }
}
