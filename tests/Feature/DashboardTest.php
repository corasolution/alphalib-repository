<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/dashboard')->assertOk();
    }

    public function test_a_user_with_no_journal_role_sees_no_journals()
    {
        Site::create(['title' => 'Test Site']);
        Journal::create(['site_id' => Site::first()->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);

        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('journals', 0));
    }

    /**
     * A site admin holds no per-journal role row by design — every JournalPolicy/SubmissionPolicy
     * before() hook grants them access without one. If this landing page only listed journals
     * from an explicit role, the one global role would be unable to reach any journal from here.
     */
    public function test_a_site_admin_sees_every_enabled_journal_without_holding_a_role_on_any()
    {
        $site = Site::create(['title' => 'Test Site']);
        Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal'], 'is_enabled' => true]);
        Journal::create(['site_id' => $site->id, 'path' => 'disabled', 'title' => ['en' => 'Disabled Journal'], 'is_enabled' => false]);

        $admin = User::factory()->create(['is_site_admin' => true]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('journals', 1)
            ->where('journals.0.path', 'demo')
        );
    }

    public function test_a_regular_editor_only_sees_journals_they_hold_a_role_on()
    {
        $site = Site::create(['title' => 'Test Site']);
        $mine = Journal::create(['site_id' => $site->id, 'path' => 'mine', 'title' => ['en' => 'Mine']]);
        Journal::create(['site_id' => $site->id, 'path' => 'other', 'title' => ['en' => 'Other']]);

        $editor = User::factory()->create();
        $mine->grantRole($editor, JournalRole::Editor);

        $response = $this->actingAs($editor)->get('/dashboard');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('journals', 1)
            ->where('journals.0.path', 'mine')
        );
    }
}
