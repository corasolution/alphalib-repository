<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\Issue;
use Alpha\Journal\Models\IssueGalley;
use Alpha\Journal\Support\UploadLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IssueGalleyController extends Controller
{
    public function store(Request $request, string $journal, Issue $issue): RedirectResponse
    {
        Gate::authorize('manage', $issue);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'file' => ['required', 'file', 'mimes:pdf,html,xml', 'max:'.UploadLimits::perFileKilobytes()],
        ]);

        IssueGalley::storeFor($issue, $request->file('file'), $data['label']);

        return back()->with('success', 'Galley uploaded.');
    }
}
