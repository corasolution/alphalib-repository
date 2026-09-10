<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Exceptions\InvitationException;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Journals\JournalInvitationService;
use Alpha\Journal\Support\JournalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function store(Request $request, string $journal, JournalInvitationService $invitations): JsonResponse
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('sendInvitations', $currentJournal);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::enum(JournalRole::class)],
        ]);

        $invitation = $invitations->invite(
            $currentJournal,
            $data['email'],
            JournalRole::from($data['role']),
            $request->user(),
        );

        return response()->json([
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'expires_at' => $invitation->expires_at,
        ], 201);
    }

    public function accept(Request $request, string $journal, string $token): JsonResponse
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        $invitation = $currentJournal->invitations()->where('token', $token)->firstOrFail();

        try {
            $invitation->accept($request->user());
        } catch (InvitationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'accepted',
            'journal' => $currentJournal->path,
            'role' => $invitation->role->value,
        ]);
    }
}
