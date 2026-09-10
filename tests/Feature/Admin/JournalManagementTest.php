<?php

namespace Tests\Feature\Admin;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\Section;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Site admins creating and administering journals. Two things carry real risk here:
 *
 *  - A journal created without a JournalManager is administrable by nobody but a site admin —
 *    nobody can submit, be invited, decide or publish. Creation must be all-or-nothing.
 *  - Disabling a journal takes it off the public web but must NOT strand its own editors, while
 *    still being completely invisible (404, not 403) to readers and non-members.
 */
class JournalManagementTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create(['title' => 'Alpha Journal']);
        $this->admin = User::factory()->create(['is_site_admin' => true]);
    }

    public function test_creating_a_journal_also_assigns_a_manager_and_a_default_section(): void
    {
        $manager = User::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('admin.journals.store'), [
            'title' => 'Journal of Applied Things',
            'path' => 'applied-things',
            'primary_locale' => 'en',
            'manager_id' => $manager->id,
        ]);

        $response->assertRedirect(route('admin.journals.index'));
        $response->assertSessionHasNoErrors();

        $journal = Journal::where('path', 'applied-things')->sole();
        $this->assertSame('Journal of Applied Things', $journal->title->get('en'));
        $this->assertTrue($journal->is_enabled);
        $this->assertTrue($manager->hasRoleOn($journal, JournalRole::JournalManager));
        $this->assertSame(1, Section::withoutGlobalScopes()->where('journal_id', $journal->id)->count());
    }

    public function test_the_assigned_manager_can_immediately_reach_the_new_journal(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.journals.store'), [
            'title' => 'Journal of Applied Things',
            'path' => 'applied-things',
            'primary_locale' => 'en',
            'manager_id' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->get(route('journal.dashboard', ['journal' => 'applied-things']))
            ->assertOk();
    }

    #[DataProvider('badPaths')]
    public function test_invalid_paths_are_rejected(string $path): void
    {
        $manager = User::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('admin.journals.store'), [
            'title' => 'Some Journal',
            'path' => $path,
            'primary_locale' => 'en',
            'manager_id' => $manager->id,
        ]);

        $response->assertSessionHasErrors('path');
        $this->assertSame(0, Journal::count());
    }

    public static function badPaths(): array
    {
        return [
            'spaces' => ['my journal'],
            'slash' => ['my/journal'],
            'uppercase' => ['MyJournal'],
            'leading hyphen' => ['-journal'],
            'empty' => [''],
        ];
    }

    public function test_duplicate_paths_are_rejected(): void
    {
        Journal::create(['site_id' => $this->site->id, 'path' => 'taken', 'title' => ['en' => 'Taken']]);
        $manager = User::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('admin.journals.store'), [
            'title' => 'Another',
            'path' => 'taken',
            'primary_locale' => 'en',
            'manager_id' => $manager->id,
        ]);

        $response->assertSessionHasErrors('path');
        $this->assertSame(1, Journal::count());
    }

    public function test_an_admin_can_rename_and_disable_a_journal(): void
    {
        $journal = Journal::create(['site_id' => $this->site->id, 'path' => 'demo', 'title' => ['en' => 'Demo']]);

        $response = $this->actingAs($this->admin)->put(route('admin.journals.update', ['journal' => $journal->id]), [
            'title' => 'Renamed Journal',
            'path' => 'renamed',
            'primary_locale' => 'en',
            'is_enabled' => false,
        ]);

        $response->assertRedirect(route('admin.journals.index'));
        $journal->refresh();
        $this->assertSame('Renamed Journal', $journal->title->get('en'));
        $this->assertSame('renamed', $journal->path);
        $this->assertFalse($journal->is_enabled);
    }

    public function test_editing_preserves_titles_in_other_locales(): void
    {
        $journal = Journal::create([
            'site_id' => $this->site->id,
            'path' => 'demo',
            'title' => ['en' => 'Demo', 'km' => 'ដេម៉ូ'],
            'primary_locale' => 'en',
        ]);

        $this->actingAs($this->admin)->put(route('admin.journals.update', ['journal' => $journal->id]), [
            'title' => 'Demo Renamed',
            'path' => 'demo',
            'primary_locale' => 'en',
            'is_enabled' => true,
        ])->assertSessionHasNoErrors();

        $journal->refresh();
        $this->assertSame('Demo Renamed', $journal->title->get('en'));
        $this->assertSame('ដេម៉ូ', $journal->title->get('km'));
    }

    public function test_ai_consent_can_be_granted_and_revoked_for_a_journal(): void
    {
        $journal = Journal::create([
            'site_id' => $this->site->id, 'path' => 'demo', 'title' => ['en' => 'Demo'],
        ]);

        // Off until explicitly granted (CLAUDE.md §6 guardrail 2).
        $this->assertFalse((bool) $journal->setting('ai_enabled', false));

        $payload = ['title' => 'Demo', 'path' => 'demo', 'primary_locale' => 'en', 'is_enabled' => true];

        $this->actingAs($this->admin)
            ->put(route('admin.journals.update', ['journal' => $journal->id]), $payload + ['ai_enabled' => true])
            ->assertSessionHasNoErrors();

        $this->assertTrue((bool) $journal->fresh()->setting('ai_enabled', false));

        $this->actingAs($this->admin)
            ->put(route('admin.journals.update', ['journal' => $journal->id]), $payload + ['ai_enabled' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse((bool) $journal->fresh()->setting('ai_enabled', false));
    }

    public function test_an_update_that_does_not_mention_ai_leaves_consent_untouched(): void
    {
        $journal = Journal::create([
            'site_id' => $this->site->id, 'path' => 'demo', 'title' => ['en' => 'Demo'],
        ]);

        $this->actingAs($this->admin)->put(route('admin.journals.update', ['journal' => $journal->id]), [
            'title' => 'Demo', 'path' => 'demo', 'primary_locale' => 'en',
            'is_enabled' => true, 'ai_enabled' => true,
        ]);

        // Renaming a journal must not revoke its AI consent as a side effect — the same partial-
        // save trap the CMS settings hit, where an omitted field silently erased stored state.
        $this->actingAs($this->admin)->put(route('admin.journals.update', ['journal' => $journal->id]), [
            'title' => 'Demo Renamed', 'path' => 'demo', 'primary_locale' => 'en', 'is_enabled' => true,
        ])->assertSessionHasNoErrors();

        $this->assertTrue((bool) $journal->fresh()->setting('ai_enabled', false));
    }

    public function test_the_index_lists_disabled_journals_too(): void
    {
        Journal::create(['site_id' => $this->site->id, 'path' => 'on', 'title' => ['en' => 'On'], 'is_enabled' => true]);
        Journal::create(['site_id' => $this->site->id, 'path' => 'off', 'title' => ['en' => 'Off'], 'is_enabled' => false]);

        $response = $this->actingAs($this->admin)->get(route('admin.journals.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/journals/index')
            ->has('journals', 2)
        );
    }

    public function test_a_non_site_admin_cannot_reach_or_mutate_journal_administration(): void
    {
        $journal = Journal::create(['site_id' => $this->site->id, 'path' => 'demo', 'title' => ['en' => 'Demo']]);
        $user = User::factory()->create(['is_site_admin' => false]);
        $journal->grantRole($user, JournalRole::JournalManager);

        $this->actingAs($user)->get(route('admin.journals.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.journals.create'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.journals.edit', ['journal' => $journal->id]))->assertForbidden();
        $this->actingAs($user)->post(route('admin.journals.store'), [
            'title' => 'Sneaky', 'path' => 'sneaky', 'primary_locale' => 'en', 'manager_id' => $user->id,
        ])->assertForbidden();
        $this->actingAs($user)->put(route('admin.journals.update', ['journal' => $journal->id]), [
            'title' => 'Hijacked', 'path' => 'demo', 'primary_locale' => 'en', 'is_enabled' => true,
        ])->assertForbidden();

        $this->assertSame(1, Journal::count());
        $this->assertSame('Demo', $journal->fresh()->title->get('en'));
    }

    // ---- Disabled-journal access matrix -------------------------------------------------

    private function disabledJournal(): Journal
    {
        return Journal::create([
            'site_id' => $this->site->id,
            'path' => 'offline',
            'title' => ['en' => 'Offline Journal'],
            'is_enabled' => false,
        ]);
    }

    public function test_a_guest_gets_404_on_a_disabled_journals_public_page(): void
    {
        $this->disabledJournal();

        $this->get('/journal/offline')->assertNotFound();
    }

    public function test_an_authenticated_non_member_gets_404_on_a_disabled_journal(): void
    {
        $this->disabledJournal();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('journal.dashboard', ['journal' => 'offline']))->assertNotFound();
    }

    public function test_a_user_with_a_role_can_still_work_in_a_disabled_journal(): void
    {
        $journal = $this->disabledJournal();
        $editor = User::factory()->create();
        $journal->grantRole($editor, JournalRole::Editor);

        $this->actingAs($editor)->get(route('journal.dashboard', ['journal' => 'offline']))->assertOk();
    }

    public function test_a_site_admin_can_still_reach_a_disabled_journal(): void
    {
        $this->disabledJournal();

        $this->actingAs($this->admin)->get(route('journal.dashboard', ['journal' => 'offline']))->assertOk();
    }

    public function test_an_enabled_journal_is_still_reachable_by_a_guest(): void
    {
        Journal::create(['site_id' => $this->site->id, 'path' => 'live', 'title' => ['en' => 'Live'], 'is_enabled' => true]);

        $this->get('/journal/live')->assertOk();
    }

    public function test_a_disabled_journal_stays_out_of_every_public_surface(): void
    {
        $this->disabledJournal();
        Journal::create(['site_id' => $this->site->id, 'path' => 'live', 'title' => ['en' => 'Live'], 'is_enabled' => true]);

        $this->get('/journals')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('journals', 1)
            ->where('journals.0.path', 'live')
        );

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('journals', 1)
            ->where('journals.0.path', 'live')
        );

        // OaiRepository filters is_enabled in its own queries; this guards the middleware change
        // from quietly exposing a disabled journal's records to harvesters.
        $oai = $this->get('/oai?verb=ListSets');
        $oai->assertOk();
        $oai->assertDontSee('offline');
    }
}
