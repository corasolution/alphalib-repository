<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Ai\AiRunner;
use Alpha\Journal\Ai\DocumentTextExtractor;
use Alpha\Journal\Ai\MetadataExtractor;
use Alpha\Journal\Enums\AiFeature;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\AiRun;
use Alpha\Journal\Support\JournalContext;
use Alpha\Journal\Support\UploadLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * A1 pre-fill (CLAUDE.md §6, §13.1). The author drops a manuscript, this reads it, and the
 * submission form comes back filled in for them to confirm and correct.
 *
 * Deliberately prefill-only: it creates no submission, writes no file, and touches no editorial
 * state. SubmissionsCreateController::store() is untouched and remains the only way a submission
 * comes into existence — so if this endpoint is slow, broken, or switched off, the author simply
 * fills the form in by hand exactly as they do today.
 *
 * The file is uploaded twice (once here, once with the real submit). That is the deliberate
 * trade for holding no server-side temp state: no orphaned files to garbage-collect, and no draft
 * submissions leaking into editor queues. At typical manuscript sizes the cost is invisible.
 */
class SubmissionAnalysisController extends Controller
{
    public function __invoke(
        Request $request,
        string $journal,
        MetadataExtractor $extractor,
        DocumentTextExtractor $text,
        AiRunner $runner,
    ): JsonResponse {
        $currentJournal = app(JournalContext::class)->requireJournal();

        // Same gate as creating a submission: if you may not submit here, you may not spend this
        // journal's AI budget probing what the model says about a document.
        Gate::authorize('createSubmission', $currentJournal);

        $request->validate([
            'manuscript' => ['required', 'file', 'mimes:pdf,doc,docx,odt', 'max:'.UploadLimits::perFileKilobytes()],
        ]);

        // isAvailableFor, not isEnabledFor: a journal with AI switched on but no API key
        // configured must look like a journal without AI, not like one whose author sent an
        // unreadable manuscript. The old check let every upload burn a round trip, log a
        // failed run, and blame the file for a server misconfiguration.
        if (! $runner->isAvailableFor($currentJournal, AiFeature::MetadataExtraction)) {
            return response()->json(['status' => 'disabled'], 200);
        }

        $file = $request->file('manuscript');

        if (! $text->canExtract($file->getClientOriginalName())) {
            // .doc and .odt are valid submissions but not readable here. Not an error — the form
            // just stays manual, which is the pre-AI behaviour.
            return response()->json(['status' => 'unsupported'], 200);
        }

        try {
            $result = $extractor->extract(
                $currentJournal,
                $file->getRealPath(),
                $file->getClientOriginalName(),
            );
        } catch (RuntimeException $e) {
            // Unreadable file (a scanned PDF, most often). The author is told plainly and carries
            // on filling the form themselves — this must never block a submission.
            return response()->json(['status' => 'unreadable', 'message' => $e->getMessage()], 200);
        }

        if ($result['metadata'] === null) {
            return response()->json(['status' => 'failed'], 200);
        }

        return response()->json([
            'status' => 'ok',
            'run_id' => $result['run']->id,
            'metadata' => $result['metadata'],
        ]);
    }

    /**
     * Closes the improvement loop (§6 guardrail 3). Called when the author submits, recording
     * whether they kept what the model proposed. Without this, ai_runs is a log; with it, it is
     * evidence about whether a prompt version is actually any good.
     */
    public function feedback(Request $request, string $journal): JsonResponse
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('createSubmission', $currentJournal);

        $data = $request->validate([
            'run_id' => ['required', 'integer'],
            'accepted' => ['required', 'boolean'],
        ]);

        // Journal-scoped lookup, so one journal cannot stamp a verdict on another's run.
        $run = AiRun::where('journal_id', $currentJournal->id)->find($data['run_id']);

        if ($run) {
            $run->markReviewed($data['accepted'], $request->user());
        }

        return response()->json(['status' => 'ok']);
    }
}
