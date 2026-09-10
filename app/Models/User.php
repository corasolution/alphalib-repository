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
        'notification_preferences',
        'telegram_chat_id',
        'telegram_linked_at',
        'telegram_link_token',
        'telegram_link_expires_at',
    ];

    /**
     * Mirrors the DB column defaults, the same fix and for the same reason as Journal::$attributes.
     *
     * Postgres does not return unspecified columns back into the model on insert, so a freshly
     * created User has is_site_admin === null in memory until it is re-fetched. That is not
     * merely untidy: SitePolicy::manage() is typed `: bool`, so a null walked straight into a
     * TypeError and every CMS route answered 500 instead of 403 for such a user.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_site_admin' => false,
        'is_suspended' => false,
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
        // A live link token lets whoever holds it attach their Telegram to this account.
        'telegram_link_token',
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
            'notification_preferences' => 'array',
            'telegram_linked_at' => 'datetime',
            'telegram_link_expires_at' => 'datetime',
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
