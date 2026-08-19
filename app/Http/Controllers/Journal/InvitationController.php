<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Exceptions\InvitationException;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Mail\EmailTemplateRenderer;
use Alpha\Journal\Notifications\TemplatedEmailNotification;
use Alpha\Journal\Support\JournalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function store(Request $request, string $journal, EmailTemplateRenderer $renderer): JsonResponse
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('sendInvitations', $currentJournal);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::enum(JournalRole::class)],
        ]);

        $invitation = $currentJournal->inviteUser($data['email'], JournalRole::from($data['role']), $request->user());

        $locale = $currentJournal->primary_locale;
        $rendered = $renderer->render($currentJournal, 'journal.invitation', $locale, [
            'journal_title' => $currentJournal->title->get($locale, 'en') ?? $currentJournal->path,
            'inviter_name' => $request->user()->name,
            'role_label' => $invitation->role->label(),
            'invitation_url' => route('journal.invitations.accept', ['journal' => $currentJournal->path, 'token' => $invitation->token]),
            'expires_at' => $invitation->expires_at->toFormattedDateString(),
        ]);

        if ($rendered !== null) {
            (new AnonymousNotifiable)
                ->route('mail', $invitation->email)
                ->notify(new TemplatedEmailNotification($rendered));
        }

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
