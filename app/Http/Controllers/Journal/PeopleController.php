<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Journals\JournalInvitationService;
use Alpha\Journal\Models\JournalUserRole;
use Alpha\Journal\Models\User;
use Alpha\Journal\Support\Activity;
use Alpha\Journal\Support\JournalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who works on this journal, and in what capacity. Roles are per-journal (CLAUDE.md §4), so this
 * screen is the Journal Manager's own staffing tool — it never touches site-level access.
 *
 * Two ways in, because they solve different problems: granting a role works on someone who
 * already has an account, while an emailed invitation covers someone who doesn't yet.
 */
class PeopleController extends Controller
{
    public function index(Request $request, string $journal): Response
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('manageUsers', $currentJournal);

        $members = JournalUserRole::where('journal_id', $currentJournal->id)
            ->with('user')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($assignments) => [
                'userId' => $assignments->first()->user->id,
                'name' => $assignments->first()->user->name,
                'email' => $assignments->first()->user->email,
                'roles' => $assignments->map(fn ($a) => [
                    'value' => $a->role->value,
                    'label' => $a->role->label(),
                ])->sortBy('label')->values(),
            ])
            ->sortBy('name')
            ->values();

        return Inertia::render('journal/people/index', [
            'journal' => [
                'path' => $currentJournal->path,
                'title' => $currentJournal->title->get($currentJournal->primary_locale, 'en') ?? $currentJournal->path,
            ],
            'members' => $members,
            'pendingInvitations' => $currentJournal->invitations()
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->get()
                ->map(fn ($invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->label(),
                    'expiresAt' => $invitation->expires_at,
                ]),
            'roles' => collect(JournalRole::cases())
                ->map(fn ($role) => ['value' => $role->value, 'label' => $role->label()]),
        ]);
    }

    /** Grants a role to someone who already has an account, looked up by email. */
    public function store(Request $request, string $journal): RedirectResponse
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('manageUsers', $currentJournal);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::enum(JournalRole::class)],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'No account with that email. Use "Invite by email" instead to send them an invitation.',
            ]);
        }

        $role = JournalRole::from($data['role']);
        $currentJournal->grantRole($user, $role);

        Activity::log('journal.role_granted', $currentJournal, "Granted {$role->label()} to {$user->name}.", [
            'user_id' => $user->id,
            'role' => $role->value,
        ]);

        return back()->with('success', "{$user->name} is now a {$role->label()}.");
    }

    public function invite(Request $request, string $journal, JournalInvitationService $invitations): RedirectResponse
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('manageUsers', $currentJournal);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::enum(JournalRole::class)],
        ]);

        $invitations->invite($currentJournal, $data['email'], JournalRole::from($data['role']), $request->user());

        return back()->with('success', "Invitation sent to {$data['email']}.");
    }

    /**
     * Creates the account outright, for a manager staffing a journal with people who don't have
     * logins yet.
     *
     * The password is random and is never shown to the manager, returned in the response, or
     * logged — the new user sets their own via the standard reset link. A manager choosing a
     * colleague's password would mean staff handling credentials they can then use to sign in as
     * that person, which would quietly break the audit trail every editorial decision depends on.
     */
    public function storeAccount(Request $request, string $journal): RedirectResponse
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('manageUsers', $currentJournal);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(JournalRole::class)],
        ], [
            'email.unique' => 'An account with that email already exists — add them with "Add someone with an account" instead.',
        ]);

        $role = JournalRole::from($data['role']);

        $user = DB::transaction(function () use ($data, $role, $currentJournal) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::random(48)),
            ]);

            $currentJournal->grantRole($user, $role);

            Activity::log('journal.account_created', $currentJournal, "Created an account for {$user->name} as {$role->label()}.", [
                'user_id' => $user->id,
                'role' => $role->value,
            ]);

            return $user;
        });

        // Standard reset flow — the new user picks their own password from a tokenised link.
        Password::sendResetLink(['email' => $user->email]);

        return back()->with('success', "Account created for {$user->name}. They've been emailed a link to set their password.");
    }

    public function destroy(Request $request, string $journal, User $user, string $role): RedirectResponse
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('manageUsers', $currentJournal);

        $journalRole = JournalRole::tryFrom($role);

        if (! $journalRole) {
            abort(404);
        }

        // Removing the last Journal Manager would leave the journal administrable by nobody but
        // a site admin — the same orphaning this app guards against at journal creation.
        if ($journalRole === JournalRole::JournalManager && $this->managerCount($currentJournal->id) <= 1) {
            throw ValidationException::withMessages([
                'role' => 'This is the only Journal Manager. Add another before removing this one.',
            ]);
        }

        JournalUserRole::where('journal_id', $currentJournal->id)
            ->where('user_id', $user->id)
            ->where('role', $journalRole->value)
            ->delete();

        Activity::log('journal.role_revoked', $currentJournal, "Removed {$journalRole->label()} from {$user->name}.", [
            'user_id' => $user->id,
            'role' => $journalRole->value,
        ]);

        return back()->with('success', "Removed {$journalRole->label()} from {$user->name}.");
    }

    private function managerCount(int $journalId): int
    {
        return JournalUserRole::where('journal_id', $journalId)
            ->where('role', JournalRole::JournalManager->value)
            ->count();
    }
}
