<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\StageAssignment;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\User;
use Alpha\Journal\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Who is editorially responsible for a submission at its current stage.
 *
 * This is what SubmissionWorkflow::guardStageHasEditor() checks before allowing any decision, and
 * until now nothing in the application created one — only seeders and tests did. Every manuscript
 * an author submitted was therefore stuck at the first decision, and the dashboard's "no editor
 * assigned" queue card counted the problem without offering a way to fix it.
 */
class StageAssignmentController extends Controller
{
    /** The roles that carry decision authority, and so the only ones assignable here. */
    public const EDITORIAL_ROLES = [
        JournalRole::Editor,
        JournalRole::SectionEditor,
        JournalRole::GuestEditor,
    ];

    public function store(Request $request, string $journal, Submission $submission): RedirectResponse
    {
        Gate::authorize('assignEditor', $submission);

        $roles = array_map(fn (JournalRole $role) => $role->value, self::EDITORIAL_ROLES);

        $data = $request->validate([
            'user_id' => [
                'required', 'integer',
                // Must already hold an editorial role on THIS journal. Without this an author
                // could be assigned to their own submission and gain decision authority over it.
                Rule::exists('journal_user_roles', 'user_id')
                    ->where('journal_id', $submission->journal_id)
                    ->whereIn('role', $roles),
            ],
        ], [
            'user_id.exists' => 'That person does not hold an editor role on this journal.',
        ]);

        $user = User::findOrFail($data['user_id']);

        // The most senior editorial role they hold, so a Journal Manager who is also an Editor is
        // recorded as an Editor rather than by whichever row came back first.
        $role = $this->editorialRoleFor($user, $submission);

        // firstOrCreate: assigning twice is a no-op rather than a duplicate row that would then
        // need removing twice.
        $assignment = StageAssignment::firstOrCreate([
            'submission_id' => $submission->id,
            'user_id' => $user->id,
            'stage' => $submission->stage->value,
            'role' => $role->value,
        ]);

        if ($assignment->wasRecentlyCreated) {
            Activity::log('submission.editor_assigned', $submission, "Assigned {$user->name} as {$role->label()}.", [
                'user_id' => $user->id,
                'stage' => $submission->stage->value,
                'role' => $role->value,
            ]);
        }

        return back()->with('success', "{$user->name} is now assigned to this submission.");
    }

    public function destroy(string $journal, Submission $submission, StageAssignment $stageAssignment): RedirectResponse
    {
        Gate::authorize('assignEditor', $submission);

        // Journal-scoped by way of the submission: an id from another submission must not resolve.
        abort_unless($stageAssignment->submission_id === $submission->id, 404);

        $name = $stageAssignment->user?->name ?? 'Someone';
        $stageAssignment->delete();

        Activity::log('submission.editor_unassigned', $submission, "Removed {$name} from this submission.", [
            'user_id' => $stageAssignment->user_id,
            'stage' => $stageAssignment->stage,
        ]);

        return back()->with('success', "{$name} is no longer assigned.");
    }

    /** The highest-authority editorial role this user holds on the submission's journal. */
    private function editorialRoleFor(User $user, Submission $submission): JournalRole
    {
        foreach (self::EDITORIAL_ROLES as $role) {
            if ($user->hasRoleOn($submission->journal, $role)) {
                return $role;
            }
        }

        // Unreachable: validation above already required one of these roles.
        return JournalRole::Editor;
    }
}
