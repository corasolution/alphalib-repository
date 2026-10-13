<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\PlagiarismCheck;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Support\Activity;
use Alpha\Journal\Support\UploadLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Recording similarity screening against a manuscript.
 *
 * Same authority as an editorial decision: screening is an editorial act, its result decides
 * whether a manuscript reaches reviewers, and it is quoted back to authors — so it is not
 * something a copyeditor or a reviewer gets to assert.
 */
class PlagiarismCheckController extends Controller
{
    public function store(Request $request, string $journal, Submission $submission): RedirectResponse
    {
        Gate::authorize('recordDecision', $submission);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(PlagiarismCheck::STATUSES))],
            // A percentage, so bounded at both ends — a similarity of 140% is a typo, not a result.
            'similarity_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tool' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'report' => ['nullable', 'file', 'mimes:pdf,html,htm', 'max:'.UploadLimits::perFileKilobytes()],
        ]);

        $check = PlagiarismCheck::record(
            $submission,
            $request->user(),
            $data['status'],
            $data['similarity_percent'] ?? null,
            $data['tool'] ?? null,
            $data['notes'] ?? null,
            $request->file('report'),
        );

        Activity::log(
            'submission.plagiarism_checked',
            $submission,
            trim($check->statusLabel().($check->similarity_percent !== null ? " at {$check->similarity_percent}% similarity" : '')),
        );

        return back()->with('success', 'Similarity check recorded.');
    }

    public function report(string $journal, Submission $submission, PlagiarismCheck $plagiarismCheck): RedirectResponse
    {
        Gate::authorize('recordDecision', $submission);

        // Route-model binding alone would happily serve another submission's report.
        abort_unless($plagiarismCheck->submission_id === $submission->id, 404);
        abort_unless($plagiarismCheck->hasReport(), 404);

        return redirect()->away($plagiarismCheck->url());
    }
}
