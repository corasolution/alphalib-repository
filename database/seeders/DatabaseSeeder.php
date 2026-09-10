<?php

namespace Database\Seeders;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\JournalSetting;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. One Site per installation (CLAUDE.md §2), one demo
     * journal, one site admin, and one journal manager to exercise the P0 role model locally.
     */
    public function run(): void
    {
        $site = Site::firstOrCreate([], [
            'title' => 'Alpha Journal',
            'primary_locale' => 'en',
            'installed_locales' => ['en', 'km'],
        ]);

        $admin = User::factory()->create([
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'is_site_admin' => true,
        ]);

        $manager = User::factory()->create([
            'name' => 'Journal Manager',
            'email' => 'manager@example.com',
        ]);

        $journal = Journal::firstOrCreate(
            ['path' => 'demo'],
            [
                'site_id' => $site->id,
                'title' => ['en' => 'Cambodian Journal of Agriculture', 'km' => 'ទស្សនាវដ្ដីកសិកម្មកម្ពុជា'],
                'primary_locale' => 'en',
                'installed_locales' => ['en', 'km'],
                'is_enabled' => true,
            ]
        );

        $journal->grantRole($manager, JournalRole::JournalManager);

        // AI is opt-in per journal and ships off (CLAUDE.md §6 guardrail 2). The demo journal
        // opts in explicitly so the A1 submission pre-fill is visible out of the box — this is a
        // seeded demo consenting on its own behalf, not a change to the default for real journals.
        JournalSetting::updateOrCreate(
            ['journal_id' => $journal->id, 'name' => 'ai_enabled', 'locale' => ''],
            ['value' => true],
        );

        $this->call(SiteContentSeeder::class);

        $this->command?->info("Seeded site admin: {$admin->email} / journal manager: {$manager->email} (password: password) / journal: /{$journal->path}");
    }
}
