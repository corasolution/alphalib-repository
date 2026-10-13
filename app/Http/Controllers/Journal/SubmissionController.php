<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Http\Resources\SubmissionResource;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\JournalUserRole;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Reviewers\ReviewerIdentityVisibility;
use Alpha\Journal\Workflow\SubmissionWorkflow;
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
    public function show(Request $request, string $journal, int $submission, SubmissionWorkflow $workflow): Response|SubmissionResource
    {
        if ($request->wantsJson()) {
            $model = Submission::with(['currentManuscript', 'reviewRounds.reviewAssignments.reviewer'])
                ->findOrFail($submission);

            Gate::authorize('view', $model);

            return new SubmissionResource($model);
        }

        $model = Submission::with([
            'journal', 'section', 'submittedBy', 'issue',
            'currentManuscript.uploadedBy',
            'currentPublication.galleys',
            'files.uploadedBy',
            'stageAssignments.user',
            'reviewRounds.reviewAssignments.reviewer',
            'editorialDecisions.actor', 'editorialDecisions.countersignedByUser',
            'activity.user',
        ])->findOrFail($submission);

        Gate::authorize('view', $model);

        $viewer = $request->user();

        return Inertia::render('journal/submissions/show', [
            'journal' => $journal,
            'submission' => [
                'id' => $model->id,
                'stage' => $model->stage->value,
                'stageLabel' => $model->stage->label(),
                'returnedToAuthorAt' => $model->returned_to_author_at?->toIso8601String(),
                'reviewType' => $model->review_type->value,
                'submittedAt' => $model->submitted_at,
                'section' => $model->section?->title?->get($model->journal->primary_locale, 'en'),
                'currentManuscript' => $model->currentManuscript ? [
                    'id' => $model->currentManuscript->id,
                    'originalName' => $model->currentManuscript->original_name,
                    'version' => $model->currentManuscript->version,
                    'uploadedBy' => $model->currentManuscript->uploadedBy?->name,
                ] : null,
                'issue' => $model->issue?->identification(),
                'doi' => $model->currentPublication?->doi,
            ],
            // Whether "generate" can propose anything. Offering the button on a journal with no
            // registered prefix would only ever produce an error.
            'hasDoiPrefix' => filled($model->journal->setting('doi_prefix')),
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
                'assignmentId' => $assignment->id,
                'userId' => $assignment->user->id,
                'name' => $assignment->user->name,
                'role' => $assignment->role->label(),
                'stage' => $assignment->stage->label(),
                // Only the current stage's editors govern whether a decision is allowed, so the
                // UI can show the rest as history without implying they still hold authority.
                'isCurrentStage' => $assignment->stage === $model->stage,
            ]))->values(),
            'canAssignEditors' => $viewer->can('assignEditor', $model),
            'canUploadFiles' => $viewer->can('uploadFile', $model),
            // The readable renditions a reader downloads (§7), produced during Production.
            'galleys' => $model->currentPublication?->galleys->map(fn ($galley) => [
                'id' => $galley->id,
                'label' => $galley->label,
                'originalName' => $galley->original_name,
                'size' => $galley->humanSize(),
            ])->values() ?? collect(),
            // Anyone holding an editorial role on this journal, minus those already assigned to
            // the current stage — offering a name that would be a no-op is just a dead option.
            'assignableEditors' => $viewer->can('assignEditor', $model)
                ? $this->assignableEditors($model)
                : [],
            // An empty picker means "all assigned" on a staffed journal and "nobody holds the
            // role" on an unstaffed one. The screen has to say which, because the second case
            // blocks every decision and is fixed somewhere else entirely.
            'journalHasEditors' => $this->journalHasEditors($model),
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
                'assignReviewer' => Gate::allows('assignReviewer', $model),
                'uploadFile' => Gate::allows('uploadFile', $model),
            ],
            'availableReviewers' => Gate::allows('assignReviewer', $model)
                ? $model->journal->usersWithRole(JournalRole::Reviewer)
                    ->get()
                    ->map(fn ($reviewer) => ['id' => $reviewer->id, 'name' => $reviewer->name])
                    ->values()
                : [],
            'availableIssues' => Gate::allows('recordDecision', $model) && $model->issue_id === null
                ? Issue::where('journal_id', $model->journal_id)
                    ->orderByDesc('year')
                    ->get()
                    ->map(fn ($issue) => ['id' => $issue->id, 'identification' => $issue->identification()])
                    ->values()
                : [],
            'legalDecisions' => Gate::allows('recordDecision', $model)
                ? collect($workflow->legalDecisionsFrom($model))
                    ->when(
                        ! Gate::allows('publish', $model),
                        fn ($decisions) => $decisions->reject(fn ($decision) => $decision->requiresPublishAuthority()),
                    )
                    ->values()
                    ->map(fn ($decision) => ['value' => $decision->value, 'label' => $decision->label()])
                : [],
        ]);
    }

    /**
     * Users holding an editorial role on this journal who are not already on the current stage.
     *
     * Excluding the already-assigned keeps the picker honest — offering a name whose assignment
     * would be a no-op is a dead option the person has to try before learning it does nothing.
     *
     * @return array<int, array{id: int, name: string, role: string}>
     */
    private function journalHasEditors(Submission $submission): bool
    {
        return JournalUserRole::where('journal_id', $submission->journal_id)
            ->whereIn('role', array_map(
                fn (JournalRole $role) => $role->value,
                StageAssignmentController::EDITORIAL_ROLES,
            ))
            ->exists();
    }

    private function assignableEditors(Submission $submission): array
    {
        $alreadyAssigned = $submission->stageAssignments
            ->where('stage', $submission->stage)
            ->pluck('user_id')
            ->all();

        $roles = array_map(
            fn (JournalRole $role) => $role->value,
            StageAssignmentController::EDITORIAL_ROLES,
        );

        return JournalUserRole::where('journal_id', $submission->journal_id)
            ->whereIn('role', $roles)
            ->whereNotIn('user_id', $alreadyAssigned)
            ->with('user')
            ->get()
            ->filter(fn ($row) => $row->user !== null)
            ->unique('user_id')
            ->sortBy(fn ($row) => $row->user->name)
            ->map(fn ($row) => [
                'id' => $row->user->id,
                'name' => $row->user->name,
                'role' => $row->role->label(),
            ])
            ->values()
            ->all();
    }
}
