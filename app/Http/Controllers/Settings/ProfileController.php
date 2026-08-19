<?php

namespace Alpha\Journal\Http\Controllers\Settings;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Http\Requests\Settings\ProfileUpdateRequest;
use Alpha\Journal\Privacy\AccountAnonymizationService;
use Alpha\Journal\Privacy\UserDataExportService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * "Delete" the user's account — CLAUDE.md §11.5 requires an editorial-record retention
     * policy, so this anonymizes rather than removes the row. The DB itself now refuses a hard
     * delete on any account with editorial history (see the restrictOnDelete migration), so this
     * is the only path that actually works for an account that's ever authored, reviewed, or
     * decided anything.
     */
    public function destroy(Request $request, AccountAnonymizationService $service): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $service->anonymize($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Machine-readable export of all personal data across submissions and reviews
     * (CLAUDE.md §11.5).
     */
    public function exportData(Request $request, UserDataExportService $service): JsonResponse
    {
        return response()->json($service->export($request->user()));
    }
}
