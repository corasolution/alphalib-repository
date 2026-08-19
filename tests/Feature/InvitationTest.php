<?php

namespace Tests\Feature;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\JournalInvitation;
use Alpha\Journal\Models\Site;
use Alpha\Journal\Models\User;
use Alpha\Journal\Notifications\TemplatedEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private Journal $journal;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['title' => 'Test Site']);
        $this->journal = Journal::create(['site_id' => $site->id, 'path' => 'demo', 'title' => ['en' => 'Journal A']]);
        $this->manager = User::factory()->create();
        $this->journal->grantRole($this->manager, JournalRole::JournalManager);
    }

    public function test_a_journal_manager_can_invite_a_user_by_email(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->manager)->postJson('/journal/demo/invitations', [
            'email' => 'newreviewer@example.com',
            'role' => JournalRole::Reviewer->value,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('journal_invitations', [
            'journal_id' => $this->journal->id,
            'email' => 'newreviewer@example.com',
            'role' => JournalRole::Reviewer->value,
        ]);
        Notification::assertSentOnDemand(TemplatedEmailNotification::class);
    }

    public function test_a_non_manager_cannot_send_invitations(): void
    {
        $author = User::factory()->create();
        $this->journal->grantRole($author, JournalRole::Author);

        $response = $this->actingAs($author)->postJson('/journal/demo/invitations', [
            'email' => 'x@example.com',
            'role' => JournalRole::Reviewer->value,
        ]);

        $response->assertForbidden();
    }

    public function test_an_invalid_role_is_rejected(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/journal/demo/invitations', [
            'email' => 'x@example.com',
            'role' => 'not-a-real-role',
        ]);

        $response->assertUnprocessable();
    }

    public function test_accepting_a_valid_invitation_grants_the_role(): void
    {
        $invitedUser = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = $this->journal->inviteUser('invitee@example.com', JournalRole::SectionEditor, $this->manager);

        $response = $this->actingAs($invitedUser)
            ->getJson("/journal/demo/invitations/{$invitation->token}/accept");

        $response->assertOk()->assertJson(['status' => 'accepted']);
        $this->assertTrue($invitedUser->hasRoleOn($this->journal, JournalRole::SectionEditor));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_accepting_with_a_mismatched_email_is_rejected(): void
    {
        $wrongUser = User::factory()->create(['email' => 'someone-else@example.com']);
        $invitation = $this->journal->inviteUser('invitee@example.com', JournalRole::Reviewer, $this->manager);

        $response = $this->actingAs($wrongUser)
            ->getJson("/journal/demo/invitations/{$invitation->token}/accept");

        $response->assertUnprocessable();
        $this->assertFalse($wrongUser->hasRoleOn($this->journal, JournalRole::Reviewer));
    }

    public function test_an_expired_invitation_cannot_be_accepted(): void
    {
        $invitedUser = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = $this->journal->inviteUser('invitee@example.com', JournalRole::Reviewer, $this->manager);
        $invitation->update(['expires_at' => now()->subDay()]);

        $response = $this->actingAs($invitedUser)
            ->getJson("/journal/demo/invitations/{$invitation->token}/accept");

        $response->assertUnprocessable();
    }

    public function test_an_invitation_can_only_be_accepted_once(): void
    {
        $invitedUser = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = $this->journal->inviteUser('invitee@example.com', JournalRole::Reviewer, $this->manager);
        $invitation->accept($invitedUser);

        $response = $this->actingAs($invitedUser)
            ->getJson("/journal/demo/invitations/{$invitation->token}/accept");

        $response->assertUnprocessable();
        $this->assertSame(1, JournalInvitation::where('id', $invitation->id)->count());
    }

    public function test_a_guest_cannot_accept_an_invitation(): void
    {
        $invitation = $this->journal->inviteUser('invitee@example.com', JournalRole::Reviewer, $this->manager);

        $response = $this->getJson("/journal/demo/invitations/{$invitation->token}/accept");

        $response->assertUnauthorized();
    }
}
