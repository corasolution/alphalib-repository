<?php

namespace Alpha\Journal\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Models\Scopes\JournalScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_site_admin',
        'is_suspended',
        'suspended_at',
        'orcid',
        'orcid_access_token',
        'orcid_token_expires_at',
        'orcid_authenticated_at',
        'anonymized_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'orcid_access_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_site_admin' => 'boolean',
            'is_suspended' => 'boolean',
            'suspended_at' => 'datetime',
            'orcid_access_token' => 'encrypted',
            'orcid_token_expires_at' => 'datetime',
            'orcid_authenticated_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    /**
     * User disable/suspend without deletion (CLAUDE.md §11.4) — site-wide, blocks login
     * everywhere. LoginRequest::authenticate() enforces this.
     */
    public function suspend(): void
    {
        $this->update(['is_suspended' => true, 'suspended_at' => now()]);
    }

    public function reactivate(): void
    {
        $this->update(['is_suspended' => false, 'suspended_at' => null]);
    }

    public function hasAuthenticatedOrcid(): bool
    {
        return $this->orcid_authenticated_at !== null && $this->orcid_access_token !== null;
    }

    public function reviewerProfileOn(Journal $journal): ?ReviewerProfile
    {
        return ReviewerProfile::where('journal_id', $journal->id)->where('user_id', $this->id)->first();
    }

    public function journalRoles(): HasMany
    {
        return $this->hasMany(JournalUserRole::class)->withoutGlobalScope(JournalScope::class);
    }

    /**
     * Roles are per-journal, not global (CLAUDE.md §4) — this user may be an Author on one
     * journal and an Editor on another.
     */
    public function hasRoleOn(Journal $journal, JournalRole $role): bool
    {
        return $this->journalRoles()
            ->where('journal_id', $journal->id)
            ->where('role', $role->value)
            ->exists();
    }

    /** @return JournalRole[] */
    public function rolesOn(Journal $journal): array
    {
        return $this->journalRoles()
            ->where('journal_id', $journal->id)
            ->get()
            ->map(fn (JournalUserRole $assignment) => $assignment->role)
            ->all();
    }
}
