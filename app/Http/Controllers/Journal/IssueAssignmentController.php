<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Assigns a submission to an issue — the prerequisite `SubmissionWorkflow::guardIssueAssignedForPublish`
 * already enforces before `publish` is legal (app/Workflow/SubmissionWorkflow.php). This is
 * metadata assignment, not a stage transition, so it's a separate write path from
 * DecisionController rather than another EditorialDecisionType.
 */
class IssueAssignmentController extends Controller
{
    public function store(Request $request, string $journal, Submission $submission): RedirectResponse
    {
        Gate::authorize('recordDecision', $submission);

        $data = $request->validate([
            'issue_id' => [
                'required',
                'integer',
                Rule::exists('issues', 'id')->where('journal_id', $submission->journal_id),
            ],
        ]);

        $submission->update(['issue_id' => $data['issue_id']]);

        return back()->with('success', 'Submission assigned to issue.');
    }
}
