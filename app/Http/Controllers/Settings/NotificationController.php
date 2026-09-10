<?php

namespace Alpha\Journal\Http\Controllers\Settings;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Notifications\NotificationPreferences;
use Alpha\Journal\Notifications\Telegram\TelegramLinker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A user's own notification channels and mutes (CLAUDE.md §11.1).
 *
 * Everything here operates on $request->user() and never on an id from the request, so there is
 * no path by which one user reads or changes another's preferences.
 */
class NotificationController extends Controller
{
    /** Templates a user may mute, grouped for the screen. Keys match resources/mail/templates. */
    public const MUTABLE = [
        'Reviewing' => [
            'review.invitation' => 'Invitations to review',
            'review.reminder_upcoming' => 'Reminder before a review is due',
            'review.reminder_overdue' => 'Reminder when a review is overdue',
        ],
        'Editing' => [
            'review.overdue_escalation' => 'A review you are waiting on is overdue',
            'submission.unclaimed_alert' => 'A submission has no editor assigned',
        ],
        'My submissions' => [
            'submission.send_to_review' => 'Sent for review',
            'submission.accepted' => 'Accepted',
            'submission.revisions_requested' => 'Revisions requested',
            'submission.declined' => 'Declined',
            'submission.published' => 'Published',
        ],
    ];

    public function edit(Request $request, NotificationPreferences $preferences, TelegramLinker $linker): Response
    {
        $user = $request->user();

        return Inertia::render('settings/notifications', [
            'preferences' => $preferences->forDisplay($user),
            'groups' => self::MUTABLE,
            // Issued per page load: the token is single-use, so a stale one from a cached page
            // would simply fail rather than link the wrong chat.
            'telegramLink' => $preferences->forDisplay($user)['telegramLinked'] ? null : $linker->deepLinkFor($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $mutable = collect(self::MUTABLE)->flatMap(fn ($group) => array_keys($group))->all();

        $data = $request->validate([
            'mail' => ['required', 'boolean'],
            'telegram' => ['required', 'boolean'],
            'muted' => ['array'],
            // Whitelisted: an arbitrary string here would let a user store unbounded junk in a
            // column that is read on every send.
            'muted.*' => ['string', 'in:'.implode(',', $mutable)],
        ]);

        $request->user()->update(['notification_preferences' => [
            'mail' => $data['mail'],
            'telegram' => $data['telegram'],
            'muted' => array_values(array_unique($data['muted'] ?? [])),
        ]]);

        return back()->with('success', 'Notification preferences saved.');
    }

    public function unlinkTelegram(Request $request, TelegramLinker $linker): RedirectResponse
    {
        $linker->unlink($request->user());

        return back()->with('success', 'Telegram disconnected.');
    }
}
