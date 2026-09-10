<?php

namespace Tests\Feature;

use Alpha\Journal\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The handbook page.
 *
 * The test that matters is the first one: the handbook explains what submitting involves, so it
 * has to be readable by somebody who has not made an account yet. Putting it behind auth would
 * hide it from most of the people it is written for, and nothing else in the suite would notice.
 */
class HandbookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Site::create(['title' => 'BIU Journal']);
    }

    public function test_the_handbook_is_readable_without_an_account(): void
    {
        $this->get('/handbook')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('public/handbook'));
    }

    public function test_it_carries_the_site_chrome_so_the_header_and_footer_render(): void
    {
        // Without these props PublicSiteLayout renders a header with no title and no menu — the
        // page would look broken rather than fail, which is the sort of thing only a test catches.
        $this->get('/handbook')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('site.title', 'BIU Journal')
                ->has('site.logoUrl')
                ->has('menuItems')
                ->has('socialLinks'));
    }

    public function test_it_answers_on_a_stable_address(): void
    {
        // The handbook gets printed on guidance and emailed to reviewers, so the address is part
        // of the contract. (The footer link into it is rendered client-side by the layout and so
        // is verified in the browser, not here — asserting it against server HTML would only
        // prove that Inertia serialises props.)
        $this->assertSame(url('/handbook'), route('handbook'));
    }
}
