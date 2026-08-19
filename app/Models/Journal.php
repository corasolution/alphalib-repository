<?php

namespace Alpha\Journal\Models;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Models\Scopes\JournalScope;
use Corasoft\AlphaScholar\Metadata\MultilingualCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Journal extends Model
{
    protected $fillable = ['site_id', 'path', 'title', 'primary_locale', 'installed_locales', 'is_enabled'];

    /**
     * Mirrors the DB column defaults. Without this, a freshly created()/new Journal instance has
     * primary_locale === null in memory (Postgres inserts don't return unspecified columns back
     * into the model) until the row is re-fetched — a real bug that crashed
     * DoajApplicationBundle::generate() when handed the same in-memory instance a test had just
     * created, since MultilingualText::get() requires a non-null locale string.
     */
    protected $attributes = [
        'primary_locale' => 'en',
        'installed_locales' => '["en"]',
        'is_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'title' => MultilingualCast::class,
            'installed_locales' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * withoutGlobalScope: this relation is already pinned to $this->id via the FK, so the ambient
     * JournalScope (which reads request context, not this model) would only get in the way.
     */
    public function settings(): HasMany
    {
        return $this->hasMany(JournalSetting::class)->withoutGlobalScope(JournalScope::class);
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(JournalUserRole::class)->withoutGlobalScope(JournalScope::class);
    }

    /**
     * Users holding a given role on this journal (CLAUDE.md §4 — roles are per-journal).
     */
    public function usersWithRole(JournalRole $role): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'journal_user_roles')
            ->wherePivot('role', $role->value);
    }

    public function grantRole(User $user, JournalRole $role): JournalUserRole
    {
        return $this->userRoles()->firstOrCreate([
            'user_id' => $user->id,
            'role' => $role->value,
        ]);
    }

    /**
     * Reads a non-multilingual journal_settings value (locale ''), e.g. `require_dual_approval`
     * or the scheduled-task intervals in §11.2. Returns $default when unset.
     */
    public function setting(string $name, mixed $default = null): mixed
    {
        $setting = $this->settings()->where('name', $name)->where('locale', '')->first();

        return $setting?->value ?? $default;
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(JournalInvitation::class)->withoutGlobalScope(JournalScope::class);
    }

    /**
     * Invitation-based enrollment (CLAUDE.md §11.4). Row creation only — sending the email is the
     * caller's job (InvitationController), same separation used by SubmissionWorkflow: models
     * create records, listeners/controllers own I/O side effects.
     */
    public function inviteUser(string $email, JournalRole $role, User $invitedBy, int $expiresInDays = 14): JournalInvitation
    {
        return $this->invitations()->create([
            'email' => $email,
            'role' => $role->value,
            'invited_by' => $invitedBy->id,
            'token' => Str::random(40),
            'expires_at' => now()->addDays($expiresInDays),
        ]);
    }
}
