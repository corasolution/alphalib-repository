<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Enums\JournalRole;
use Alpha\Journal\Enums\ReviewType;
use Alpha\Journal\Enums\SubmissionStage;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\Contributor;
use Alpha\Journal\Models\Publication;
use Alpha\Journal\Models\Section;
use Alpha\Journal\Models\Submission;
use Alpha\Journal\Models\SubmissionFile;
use Alpha\Journal\Support\Activity;
use Alpha\Journal\Support\JournalContext;
use Alpha\Journal\Support\UploadLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Single scrolling screen with a progress rail, not sequential locked steps" (CLAUDE.md §13.1).
 * AI (A1) file-role classification and metadata extraction, and autosave-as-draft-from-first-
 * keystroke, are P5 AI-layer features gated behind P2 validation — this is the plain HTML-form
 * version of the same one-screen shape, without them. The author still confirms/corrects nothing
 * the AI hasn't proposed yet, because nothing here proposes anything; they just fill the form.
 */
class SubmissionsCreateController extends Controller
{
    public function create(Request $request, string $journal): Response
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('createSubmission', $currentJournal);

        $locale = $currentJournal->primary_locale;

        return Inertia::render('journal/submissions/create', [
            'journal' => [
                'path' => $currentJournal->path,
                'title' => $currentJournal->title->get($locale, 'en') ?? $currentJournal->path,
            ],
            'sections' => Section::orderBy('sort_order')
                ->get()
                ->map(fn ($section) => [
                    'id' => $section->id,
                    'title' => $section->title->get($locale, 'en') ?? $section->id,
                ]),
            // The real ceiling, not the policy one: PHP refuses anything past post_max_size
            // before this app sees the request, so a hint quoting 50 MB on an install capped at
            // 8 MB is how an author ends up on a 413 page with the form gone.
            'uploads' => [
                'maxFileBytes' => UploadLimits::perFileBytes(),
                'maxFileLabel' => UploadLimits::perFileLabel(),
                'maxPostBytes' => UploadLimits::postMaxBytes(),
                'maxPostLabel' => UploadLimits::postMaxLabel(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currentJournal = app(JournalContext::class)->requireJournal();

        Gate::authorize('createSubmission', $currentJournal);

        $data = $request->validate([
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'title' => ['required', 'string', 'max:500'],
            'abstract' => ['nullable', 'string', 'max:20000'],
            'cover_letter' => ['nullable', 'string', 'max:20000'],
            'manuscript' => ['required', 'file', 'mimes:pdf,doc,docx,odt', 'max:'.UploadLimits::perFileKilobytes()],
            'supporting_files' => ['nullable', 'array'],
            'supporting_files.*' => ['file', 'max:'.UploadLimits::perFileKilobytes()],
        ]);

        $user = $request->user();
        $locale = $currentJournal->primary_locale;

        $submission = DB::transaction(function () use ($data, $user, $currentJournal, $locale, $request) {
            $submission = Submission::create([
                'journal_id' => $currentJournal->id,
                'section_id' => $data['section_id'] ?? null,
                'submitted_by' => $user->id,
                'stage' => SubmissionStage::Submission->value,
                'review_type' => ReviewType::DoubleAnonymous->value,
                'submitted_at' => now(),
            ]);

            $publication = Publication::create([
                'submission_id' => $submission->id,
                'version' => 1,
                'title' => [$locale => $data['title']],
                'abstract' => isset($data['abstract']) ? [$locale => $data['abstract']] : null,
                'status' => 'draft',
            ]);

            $submission->update(['current_publication_id' => $publication->id]);

            [$givenName, $familyName] = self::splitName($user->name);
            Contributor::create([
                'publication_id' => $publication->id,
                'given_name' => $givenName,
                'family_name' => $familyName,
                'email' => $user->email,
                'orcid' => $user->orcid,
                'role' => 'author',
                'sequence' => 0,
            ]);

            SubmissionFile::storeManuscript($submission, $request->file('manuscript'), $user);

            foreach ($request->file('supporting_files', []) as $supporting) {
                SubmissionFile::storeSupporting($submission, $supporting, $user);
            }

            if (! empty($data['cover_letter'])) {
                Activity::log('submission.cover_letter', $submission, $data['cover_letter']);
            }

            $currentJournal->grantRole($user, JournalRole::Author);

            Activity::log('submission.created', $submission, "Submitted \"{$data['title']}\".");

            return $submission;
        });

        return redirect()
            ->route('journal.submissions.show', ['journal' => $currentJournal->path, 'submission' => $submission->id])
            ->with('success', 'Submission received.');
    }

    /** @return array{0: string, 1: string} */
    private static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? $name, $parts[1] ?? ''];
    }
}
