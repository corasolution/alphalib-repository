<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\JournalUserRole;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\User;
use Alpha\Journal\Notifications\TemplatedNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The Journal Manager staffing their own journal — the missing piece that meant roles could only
 * be assigned at journal creation, in a seeder, or via tinker.
 *
 * The load-bearing guard here is that the last Journal Manager can't be removed: doing so leaves
 * the journal administrable by nobody but a site admin, the same orphaning the create-journal
 * flow already prevents.
 */
class JournalPeopleTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Demo Journal']]);
        $this->manager = User::factory()->create(['name' => 'Mia Manager']);
        $this->journal->grantRole($this->manager, JournalRole::JournalManager);
    }

    private function route(string $name, array $params = []): string
    {
        return route($name, array_merge(['journal' => $this->journal->path], $params));
    }

    public function test_a_manager_sees_the_people_screen_with_current_members(): void
    {
        $editor = User::factory()->create(['name' => 'Ed Editor']);
        $this->journal->grantRole($editor, JournalRole::Editor);

        $response = $this->actingAs($this->manager)->get($this->route('journal.people.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('journal/people/index')
            ->has('members', 2)
            ->has('roles', count(JournalRole::cases()))
        );
    }

    public function test_a_manager_can_grant_a_role_to_an_existing_user(): void
    {
        $reviewer = User::factory()->create(['email' => 'rita@example.com']);

        $response = $this->actingAs($this->manager)->post($this->route('journal.people.store'), [
            'email' => 'rita@example.com',
            'role' => JournalRole::Reviewer->value,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertTrue($reviewer->hasRoleOn($this->journal, JournalRole::Reviewer));
    }

    public function test_granting_a_role_twice_does_not_duplicate_it(): void
    {
        $reviewer = User::factory()->create(['email' => 'rita@example.com']);

        foreach (range(1, 2) as $ignored) {
            $this->actingAs($this->manager)->post($this->route('journal.people.store'), [
                'email' => 'rita@example.com',
                'role' => JournalRole::Reviewer->value,
            ]);
        }

        $this->assertSame(1, JournalUserRole::where('journal_id', $this->journal->id)
            ->where('user_id', $reviewer->id)
            ->where('role', JournalRole::Reviewer->value)
            ->count());
    }

    public function test_granting_to_an_unknown_email_points_the_manager_at_invitations(): void
    {
        $response = $this->actingAs($this->manager)->post($this->route('journal.people.store'), [
            'email' => 'nobody@example.com',
            'role' => JournalRole::Reviewer->value,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_a_manager_can_invite_someone_without_an_account(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->manager)->post($this->route('journal.people.invite'), [
            'email' => 'newcomer@example.com',
            'role' => JournalRole::Reviewer->value,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame(1, $this->journal->invitations()->where('email', 'newcomer@example.com')->count());
        Notification::assertSentOnDemand(TemplatedNotification::class);
    }

    public function test_a_manager_can_revoke_a_role(): void
    {
        $editor = User::factory()->create();
        $this->journal->grantRole($editor, JournalRole::Editor);

        $response = $this->actingAs($this->manager)->delete(
            $this->route('journal.people.destroy', ['user' => $editor->id, 'role' => JournalRole::Editor->value])
        );

        $response->assertRedirect();
        $this->assertFalse($editor->fresh()->hasRoleOn($this->journal, JournalRole::Editor));
    }

    public function test_revoking_one_role_leaves_the_users_other_roles_intact(): void
    {
        $person = User::factory()->create();
        $this->journal->grantRole($person, JournalRole::Editor);
        $this->journal->grantRole($person, JournalRole::Reviewer);

        $this->actingAs($this->manager)->delete(
            $this->route('journal.people.destroy', ['user' => $person->id, 'role' => JournalRole::Editor->value])
        );

        $this->assertFalse($person->fresh()->hasRoleOn($this->journal, JournalRole::Editor));
        $this->assertTrue($person->fresh()->hasRoleOn($this->journal, JournalRole::Reviewer));
    }

    public function test_the_last_journal_manager_cannot_be_removed(): void
    {
        $response = $this->actingAs($this->manager)->delete(
            $this->route('journal.people.destroy', ['user' => $this->manager->id, 'role' => JournalRole::JournalManager->value])
        );

        $response->assertSessionHasErrors('role');
        $this->assertTrue($this->manager->fresh()->hasRoleOn($this->journal, JournalRole::JournalManager));
    }

    public function test_a_manager_can_be_removed_once_another_one_exists(): void
    {
        $second = User::factory()->create();
        $this->journal->grantRole($second, JournalRole::JournalManager);

        $response = $this->actingAs($this->manager)->delete(
            $this->route('journal.people.destroy', ['user' => $this->manager->id, 'role' => JournalRole::JournalManager->value])
        );

        $response->assertSessionHasNoErrors();
        $this->assertFalse($this->manager->fresh()->hasRoleOn($this->journal, JournalRole::JournalManager));
        $this->assertTrue($second->fresh()->hasRoleOn($this->journal, JournalRole::JournalManager));
    }

    public function test_a_non_manager_member_cannot_reach_or_change_people(): void
    {
        $editor = User::factory()->create();
        $this->journal->grantRole($editor, JournalRole::Editor);

        $this->actingAs($editor)->get($this->route('journal.people.index'))->assertForbidden();
        $this->actingAs($editor)->post($this->route('journal.people.store'), [
            'email' => $editor->email, 'role' => JournalRole::JournalManager->value,
        ])->assertForbidden();
        $this->actingAs($editor)->post($this->route('journal.people.invite'), [
            'email' => 'x@example.com', 'role' => JournalRole::Editor->value,
        ])->assertForbidden();
        $this->actingAs($editor)->delete(
            $this->route('journal.people.destroy', ['user' => $this->manager->id, 'role' => JournalRole::JournalManager->value])
        )->assertForbidden();

        $this->assertFalse($editor->fresh()->hasRoleOn($this->journal, JournalRole::JournalManager));
    }

    public function test_roles_granted_here_do_not_leak_to_another_journal(): void
    {
        $other = Journal::create(['site_id' => $this->journal->site_id, 'path' => 'other', 'title' => ['en' => 'Other']]);
        $reviewer = User::factory()->create(['email' => 'rita@example.com']);

        $this->actingAs($this->manager)->post($this->route('journal.people.store'), [
            'email' => 'rita@example.com',
            'role' => JournalRole::Reviewer->value,
        ]);

        $this->assertTrue($reviewer->hasRoleOn($this->journal, JournalRole::Reviewer));
        $this->assertFalse($reviewer->hasRoleOn($other, JournalRole::Reviewer));
    }

    public function test_a_manager_can_create_an_account_and_the_new_user_gets_a_password_link(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->manager)->post($this->route('journal.people.accounts.store'), [
            'name' => 'Nina Newcomer',
            'email' => 'nina@example.com',
            'role' => JournalRole::Reviewer->value,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'nina@example.com')->sole();
        $this->assertSame('Nina Newcomer', $user->name);
        $this->assertTrue($user->hasRoleOn($this->journal, JournalRole::Reviewer));

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_the_generated_password_is_random_and_never_disclosed(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->manager)->post($this->route('journal.people.accounts.store'), [
            'name' => 'Nina Newcomer',
            'email' => 'nina@example.com',
            'role' => JournalRole::Reviewer->value,
        ]);

        $user = User::where('email', 'nina@example.com')->sole();

        // Nothing a manager could sign in as this person with: not a guessable value, and not
        // echoed back anywhere in the response or the session.
        foreach (['password', 'secret', $user->email, 'Nina'] as $guess) {
            $this->assertFalse(Hash::check($guess, $user->password));
        }

        $response->assertSessionMissing('password');
        $this->assertStringNotContainsString($user->password, $response->getContent());
    }

    public function test_creating_an_account_for_an_existing_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($this->manager)->post($this->route('journal.people.accounts.store'), [
            'name' => 'Someone Else',
            'email' => 'taken@example.com',
            'role' => JournalRole::Reviewer->value,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
    }

    public function test_a_created_account_is_never_a_site_admin(): void
    {
        Notification::fake();

        $this->actingAs($this->manager)->post($this->route('journal.people.accounts.store'), [
            'name' => 'Nina Newcomer',
            'email' => 'nina@example.com',
            'role' => JournalRole::JournalManager->value,
        ]);

        // A journal manager can staff their own journal, but must not be able to mint
        // platform-wide administrators.
        $this->assertFalse((bool) User::where('email', 'nina@example.com')->sole()->is_site_admin);
    }

    public function test_a_non_manager_cannot_create_accounts(): void
    {
        $editor = User::factory()->create();
        $this->journal->grantRole($editor, JournalRole::Editor);

        $this->actingAs($editor)->post($this->route('journal.people.accounts.store'), [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'role' => JournalRole::Editor->value,
        ])->assertForbidden();

        $this->assertSame(0, User::where('email', 'sneaky@example.com')->count());
    }
}
