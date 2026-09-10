<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Enums\EditorialDecisionType;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Workflow\Exceptions\WorkflowAuthorizationException;
use Alpha\Journal\Workflow\Exceptions\WorkflowTransitionException;
use Alpha\Journal\Workflow\SubmissionWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The one write path for editorial decisions (§13.5: "Decisions show their email before
 * sending" — this endpoint is the send). All transition legality, authority, dual-approval, and
 * publish guards live in SubmissionWorkflow; this controller only translates its exceptions into
 * a redirect the Inertia workspace can render as a validation error, never re-implements them.
 */
class DecisionController extends Controller
{
    public function store(Request $request, string $journal, Submission $submission, SubmissionWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('recordDecision', $submission);

        $data = $request->validate([
            'decision' => ['required', Rule::enum(EditorialDecisionType::class)],
            'reason' => ['nullable', 'string', 'max:10000'],
        ]);

        $decision = EditorialDecisionType::from($data['decision']);

        if ($decision->requiresPublishAuthority()) {
            Gate::authorize('publish', $submission);
        }

        try {
            $workflow->recordDecision($submission, $request->user(), $decision, $data['reason'] ?? null);
        } catch (WorkflowTransitionException|WorkflowAuthorizationException $e) {
            throw ValidationException::withMessages(['decision' => $e->getMessage()]);
        }

        return back()->with('success', "Decision recorded: {$decision->label()}.");
    }
}
