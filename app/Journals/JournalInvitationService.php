<?php

namespace Alpha\Journal\Journals;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Mail\EmailTemplateRenderer;
use Alpha\Journal\Models\Journal;
use Alpha\Journal\Models\JournalInvitation;
use Alpha\Journal\Models\User;
use Alpha\Journal\Notifications\TemplatedNotification;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Invites someone to a journal by email — for people who don't have an account yet, so there's
 * nobody to grant a role to. Shared by the People screen and the JSON invitations endpoint so
 * the email rendering lives in exactly one place.
 */
class JournalInvitationService
{
    public function __construct(private readonly EmailTemplateRenderer $renderer) {}

    public function invite(Journal $journal, string $email, JournalRole $role, User $invitedBy): JournalInvitation
    {
        $invitation = $journal->inviteUser($email, $role, $invitedBy);

        $locale = $journal->primary_locale;
        $rendered = $this->renderer->render($journal, 'journal.invitation', $locale, [
            'journal_title' => $journal->title->get($locale, 'en') ?? $journal->path,
            'inviter_name' => $invitedBy->name,
            'role_label' => $invitation->role->label(),
            'invitation_url' => route('journal.invitations.accept', [
                'journal' => $journal->path,
                'token' => $invitation->token,
            ]),
            'expires_at' => $invitation->expires_at->toFormattedDateString(),
        ]);

        if ($rendered !== null) {
            (new AnonymousNotifiable)
                ->route('mail', $invitation->email)
                ->notify(new TemplatedNotification($rendered, 'journal.invitation'));
        }

        return $invitation;
    }
}
