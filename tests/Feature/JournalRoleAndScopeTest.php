<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\JournalSetting;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\User;
use Alpha\Journal\Support\Activity;
use Alpha\Journal\Support\JournalContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class JournalRoleAndScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_hold_different_roles_on_different_journals(): void
    {
        $site = Site::create(['title' => 'Test Site']);
        $journalA = Journal::create(['site_id' => $site->id, 'path' => 'a', 'title' => ['en' => 'Journal A']]);
        $journalB = Journal::create(['site_id' => $site->id, 'path' => 'b', 'title' => ['en' => 'Journal B']]);
        $user = User::factory()->create();

        $journalA->grantRole($user, JournalRole::Author);
        $journalB->grantRole($user, JournalRole::Editor);

        $this->assertTrue($user->hasRoleOn($journalA, JournalRole::Author));
        $this->assertFalse($user->hasRoleOn($journalA, JournalRole::Editor));
        $this->assertTrue($user->hasRoleOn($journalB, JournalRole::Editor));
    }

    public function test_journal_settings_relation_only_returns_the_owning_journals_rows(): void
    {
        $site = Site::create(['title' => 'Test Site']);
        $journalA = Journal::create(['site_id' => $site->id, 'path' => 'a', 'title' => ['en' => 'Journal A']]);
        $journalB = Journal::create(['site_id' => $site->id, 'path' => 'b', 'title' => ['en' => 'Journal B']]);

        $journalA->settings()->create(['name' => 'about', 'value' => ['en' => 'About A']]);
        $journalB->settings()->create(['name' => 'about', 'value' => ['en' => 'About B']]);

        $this->assertSame(1, $journalA->settings()->count());
        $this->assertSame('About A', $journalA->settings()->first()->value['en']);
    }

    public function test_journal_scope_denies_unscoped_queries_outside_a_resolved_journal_context(): void
    {
        config(['journal.scope_console_bypass' => false]);

        $site = Site::create(['title' => 'Test Site']);
        $journal = Journal::create(['site_id' => $site->id, 'path' => 'a', 'title' => ['en' => 'Journal A']]);
        $journal->settings()->create(['name' => 'about', 'value' => ['en' => 'About A']]);

        $this->assertFalse(app(JournalContext::class)->has());
        $this->assertSame(0, JournalSetting::count());
    }

    public function test_journal_scope_allows_queries_once_context_is_resolved(): void
    {
        $site = Site::create(['title' => 'Test Site']);
        $journalA = Journal::create(['site_id' => $site->id, 'path' => 'a', 'title' => ['en' => 'Journal A']]);
        $journalB = Journal::create(['site_id' => $site->id, 'path' => 'b', 'title' => ['en' => 'Journal B']]);
        $journalA->settings()->create(['name' => 'about', 'value' => ['en' => 'About A']]);
        $journalB->settings()->create(['name' => 'about', 'value' => ['en' => 'About B']]);

        app(JournalContext::class)->set($journalA);

        $this->assertSame(1, JournalSetting::count());
        $this->assertSame('About A', JournalSetting::first()->value['en']);
    }

    public function test_activity_log_rows_cannot_be_updated_or_deleted(): void
    {
        $site = Site::create(['title' => 'Test Site']);
        $journal = Journal::create(['site_id' => $site->id, 'path' => 'a', 'title' => ['en' => 'Journal A']]);
        $user = User::factory()->create();
        $this->actingAs($user);
        app(JournalContext::class)->set($journal);

        $log = Activity::log('journal.settings.updated', $journal, 'changed something');

        $this->assertDatabaseHas('activity_log', ['id' => $log->id, 'action' => 'journal.settings.updated']);

        $this->expectException(LogicException::class);
        $log->update(['description' => 'tampered']);
    }
}
