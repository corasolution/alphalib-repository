<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\SubmissionFile;
use Alpha\Journal\Support\UploadLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * One upload endpoint for every "a new file version showed up" moment after the initial
 * submission — the copyediting file exchange (OJS Copyediting stage) and an author's revised
 * manuscript after a request_revisions decision are both just SubmissionFile::store*() tagged
 * with whatever the submission's current stage happens to be; there is nothing stage-specific
 * about the write path itself, only about who's authorized to use it (SubmissionPolicy::uploadFile).
 */
class SubmissionFilesController extends Controller
{
    public function store(Request $request, string $journal, Submission $submission): RedirectResponse
    {
        Gate::authorize('uploadFile', $submission);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,odt', 'max:'.UploadLimits::perFileKilobytes()],
            'as_manuscript' => ['boolean'],
        ]);

        $store = ($data['as_manuscript'] ?? true) ? 'storeManuscript' : 'storeSupporting';

        SubmissionFile::{$store}($submission, $request->file('file'), $request->user());

        return back()->with('success', 'File uploaded.');
    }
}
