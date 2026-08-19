<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Exceptions\InvitationException;
use Alpha\Journal\Models\Concerns\BelongsToJournal;
use Alpha\Journal\Support\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalInvitation extends Model
{
    use BelongsToJournal;

    protected $fillable = ['journal_id', 'email', 'role', 'invited_by', 'token', 'expires_at', 'accepted_at', 'accepted_by'];

    protected function casts(): array
    {
        return [
            'role' => JournalRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Accepting user's email must match the invited email (case-insensitive) — otherwise a
     * forwarded or guessed invitation link could enroll the wrong account.
     *
     * @throws InvitationException
     */
    public function accept(User $user): JournalUserRole
    {
        if ($this->isAccepted()) {
            throw new InvitationException('This invitation has already been accepted.');
        }

        if ($this->isExpired()) {
            throw new InvitationException('This invitation has expired.');
        }

        if (strcasecmp($this->email, $user->email) !== 0) {
            throw new InvitationException('This invitation was sent to a different email address.');
        }

        $assignment = $this->journal->grantRole($user, $this->role);

        $this->update(['accepted_at' => now(), 'accepted_by' => $user->id]);

        Activity::log('journal.invitation.accepted', $this->journal, "Role: {$this->role->value}", [
            'invitation_id' => $this->id,
            'user_id' => $user->id,
        ]);

        return $assignment;
    }
}
