<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Crossref\DoiPatternGenerator;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The DOI on an article — assigned during Production, alongside the issue and the galley.
 *
 * A DOI is a promise that a URL will resolve forever, so this is deliberately an editor's explicit
 * act rather than something generated on publish. `generate` only proposes a value from the
 * journal's registered prefix; nothing is registered with Crossref or DataCite here, and an editor
 * can always type one issued elsewhere instead.
 */
class PublicationDoiController extends Controller
{
    public function update(Request $request, string $journal, Submission $submission): RedirectResponse
    {
        // Same authority as any other production-stage act on the submission.
        Gate::authorize('recordDecision', $submission);

        $data = $request->validate([
            // A DOI is "10.<registrant>/<suffix>". Loose on the suffix — registrants choose their
            // own shapes — strict on the prefix, which is the part that is always the same.
            'doi' => ['nullable', 'string', 'max:255', 'regex:#^10\.\d{4,9}/\S+$#'],
        ], [
            'doi.regex' => 'A DOI looks like 10.5555/journal.v1.i1.a42 — a 10.x prefix, a slash, then the suffix.',
        ]);

        $publication = $submission->currentPublication;

        if ($publication === null) {
            return back()->withErrors(['doi' => 'This submission has no publication to assign a DOI to.']);
        }

        $doi = trim((string) ($data['doi'] ?? ''));

        $this->guardUnique($doi, $publication);

        $publication->update(['doi' => $doi ?: null]);

        Activity::log(
            'submission.doi_assigned',
            $submission,
            $doi === '' ? 'Removed the DOI.' : "Assigned DOI {$doi}.",
        );

        return back()->with('success', $doi === '' ? 'DOI removed.' : 'DOI saved.');
    }

    /** Proposes a DOI from the journal's prefix, without saving it. */
    public function generate(string $journal, Submission $submission, DoiPatternGenerator $generator): RedirectResponse
    {
        Gate::authorize('recordDecision', $submission);

        $publication = $submission->currentPublication;

        if ($publication === null) {
            return back()->withErrors(['doi' => 'This submission has no publication to assign a DOI to.']);
        }

        try {
            $doi = $generator->generate($publication);
        } catch (InvalidArgumentException $e) {
            // The prefix lives on the journal settings screen, which is where this has to be fixed.
            return back()->withErrors([
                'doi' => 'This journal has no DOI prefix yet. Add one in Journal → Settings first.',
            ]);
        }

        $this->guardUnique($doi, $publication);

        $publication->update(['doi' => $doi]);

        Activity::log('submission.doi_assigned', $submission, "Assigned DOI {$doi} from the journal's prefix.");

        return back()->with('success', "DOI {$doi} assigned.");
    }

    /**
     * Two articles sharing a DOI is a data-integrity failure that propagates outward — into
     * Crossref, DataCite, ORCID and every citation exported from here — and is painful to undo
     * once registered. Cheaper to refuse.
     */
    private function guardUnique(string $doi, Publication $publication): void
    {
        if ($doi === '') {
            return;
        }

        $taken = Publication::withoutGlobalScopes()
            ->where('doi', $doi)
            ->where('id', '!=', $publication->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'doi' => "DOI {$doi} is already assigned to another article.",
            ]);
        }
    }
}
