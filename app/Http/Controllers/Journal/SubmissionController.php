<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Http\Resources\SubmissionResource;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Reviewers\ReviewerIdentityVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
    /**
     * One route per submission (CLAUDE.md §13.2): `/journal/{path}/submissions/{id}`. This
     * action serves two shapes from that one URL rather than splitting it — an XHR/JSON caller
     * (e.g. AnonymityLeakTest, or a future partial refresh) gets the same SubmissionResource
     * as before; a normal browser navigation gets the full three-pane Inertia workspace page.
     * journal_id scoping comes from JournalScope (bound by the `journal` route middleware before
     * this action runs), not from the {journal} path segment — the submission id alone cannot
     * cross into another journal's data (CLAUDE.md §2).
     */
    public function show(Request $request, string $journal, int $submission): Response|SubmissionResource
    {
        if ($request->wantsJson()) {
            $model = Submission::with(['currentManuscript', 'reviewRounds.reviewAssignments.reviewer'])
                ->findOrFail($submission);

            Gate::authorize('view', $model);

            return new SubmissionResource($model);
        }

        $model = Submission::with([
            'journal', 'section', 'submittedBy',
            'currentManuscript.uploadedBy',
            'files.uploadedBy',
            'stageAssignments.user',
            'reviewRounds.reviewAssignments.reviewer',
            'editorialDecisions.actor', 'editorialDecisions.countersignedByUser',
            'activity.user',
        ])->findOrFail($submission);

        Gate::authorize('view', $model);

        $viewer = $request->user();

        return Inertia::render('journal/submissions/show', [
            'submission' => [
                'id' => $model->id,
                'stage' => $model->stage->value,
                'stageLabel' => $model->stage->label(),
                'reviewType' => $model->review_type->value,
                'submittedAt' => $model->submitted_at,
                'section' => $model->section?->title?->get($model->journal->primary_locale, 'en'),
                'currentManuscript' => $model->currentManuscript ? [
                    'id' => $model->currentManuscript->id,
                    'originalName' => $model->currentManuscript->original_name,
                    'version' => $model->currentManuscript->version,
                    'uploadedBy' => $model->currentManuscript->uploadedBy?->name,
                ] : null,
            ],
            'timeline' => $model->activity
                ->sortBy('created_at')
                ->values()
                ->map(fn ($entry) => [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'description' => $entry->description,
                    'actor' => $entry->user?->name,
                    'createdAt' => $entry->created_at,
                ]),
            'reviewRounds' => $model->reviewRounds
                ->sortBy('round_number')
                ->values()
                ->map(fn ($round) => [
                    'id' => $round->id,
                    'roundNumber' => $round->round_number,
                    'status' => $round->status,
                    'assignments' => $round->reviewAssignments->map(fn ($assignment) => [
                        'id' => $assignment->id,
                        'reviewer' => ReviewerIdentityVisibility::canSee($viewer, $assignment)
                            ? $assignment->reviewer->name
                            : null,
                        'recommendation' => $assignment->completed_at !== null ? $assignment->recommendation?->value : null,
                        'invitedAt' => $assignment->invited_at,
                        'dueAt' => $assignment->due_at,
                        'respondedAt' => $assignment->responded_at,
                        'declinedAt' => $assignment->declined_at,
                        'completedAt' => $assignment->completed_at,
                    ]),
                ]),
            'decisions' => $model->editorialDecisions
                ->sortBy('created_at')
                ->values()
                ->map(fn ($decision) => [
                    'id' => $decision->id,
                    'decision' => $decision->decision->value,
                    'decisionLabel' => $decision->decision->label(),
                    'targetStage' => $decision->target_stage->label(),
                    'actor' => $decision->actor->name,
                    'reason' => $decision->reason,
                    'createdAt' => $decision->created_at,
                    'requiresCountersign' => $decision->requires_countersign,
                    'countersignedBy' => $decision->countersignedByUser?->name,
                ]),
            'people' => collect([
                ['userId' => $model->submittedBy->id, 'name' => $model->submittedBy->name, 'role' => 'Author', 'stage' => null],
            ])->concat($model->stageAssignments->map(fn ($assignment) => [
                'userId' => $assignment->user->id,
                'name' => $assignment->user->name,
                'role' => $assignment->role->label(),
                'stage' => $assignment->stage->label(),
            ]))->values(),
            'files' => $model->files
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($file) => [
                    'id' => $file->id,
                    'originalName' => $file->original_name,
                    'version' => $file->version,
                    'isCurrentManuscript' => $file->is_current_manuscript,
                    'stage' => $file->stage->label(),
                    'uploadedBy' => $file->uploadedBy?->name,
                    'size' => $file->size,
                    'createdAt' => $file->created_at,
                ]),
            'can' => [
                'recordDecision' => Gate::allows('recordDecision', $model),
                'publish' => Gate::allows('publish', $model),
            ],
        ]);
    }
}
