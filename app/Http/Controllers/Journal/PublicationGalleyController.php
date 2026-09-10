<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\PublicationGalley;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Support\Activity;
use Alpha\Journal\Support\UploadLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The readable file a reader downloads — OJS's Production-stage "create a galley" step, at the
 * article level (CLAUDE.md §7).
 *
 * Attached to the submission's CURRENT publication, so a galley always belongs to the version it
 * was produced from.
 */
class PublicationGalleyController extends Controller
{
    public function store(Request $request, string $journal, Submission $submission): RedirectResponse
    {
        // Same authority as any other file on the submission: editors and the assigned
        // copyeditor produce galleys, an outsider does not.
        Gate::authorize('uploadFile', $submission);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:30'],
            'file' => ['required', 'file', 'mimes:pdf,html,htm,xml,epub', 'max:'.UploadLimits::perFileKilobytes()],
        ]);

        $publication = $submission->currentPublication;

        if ($publication === null) {
            return back()->withErrors(['file' => 'This submission has no publication to attach a galley to.']);
        }

        $galley = PublicationGalley::storeFor($publication, $request->file('file'), $data['label']);

        Activity::log('submission.galley_added', $submission, "Added a {$galley->label} galley.", [
            'galley_id' => $galley->id,
            'publication_id' => $publication->id,
        ]);

        return back()->with('success', "{$galley->label} galley added.");
    }

    public function destroy(string $journal, Submission $submission, PublicationGalley $publicationGalley): RedirectResponse
    {
        Gate::authorize('uploadFile', $submission);

        // Scoped through the submission: an id belonging to another article must not resolve.
        abort_unless(
            $publicationGalley->publication?->submission_id === $submission->id,
            404,
        );

        $label = $publicationGalley->label;
        $publicationGalley->delete();

        Activity::log('submission.galley_removed', $submission, "Removed the {$label} galley.");

        return back()->with('success', "{$label} galley removed.");
    }
}
