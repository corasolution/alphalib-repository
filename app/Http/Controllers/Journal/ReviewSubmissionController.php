<?php

namespace Alpha\Journal\Http\Controllers\Journal;

use Alpha\Journal\Enums\ReviewRecommendation;
use Alpha\Journal\Exceptions\ReviewFormValidationException;
use Alpha\Journal\Http\Controllers\Controller;
use Alpha\Journal\Models\ReviewAssignment;
use Corasoft\AlphaScholar\Files\FileStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reviewer's own page — deliberately not the submission workspace (§13.2's three-pane page
 * is editor/author-facing and would leak decisions, the full people list, and other reviewers'
 * identities). A reviewer gets exactly: the manuscript, the review form (or a bare recommendation
 * if the section has none), and a submit action. Never route a reviewer through
 * SubmissionController::show.
 */
class ReviewSubmissionController extends Controller
{
    public function show(Request $request, string $journal, ReviewAssignment $reviewAssignment): Response
    {
        Gate::authorize('view', $reviewAssignment);

        $reviewAssignment->load(['reviewRound.submission.section.reviewForm.elements', 'reviewRound.submission.currentManuscript', 'reviewRound.submission.journal']);
        $submission = $reviewAssignment->reviewRound->submission;
        $locale = $submission->journal->primary_locale;
        $manuscript = $submission->currentManuscript;

        return Inertia::render('journal/reviews/show', [
            'journal' => $journal,
            'reviewAssignment' => [
                'id' => $reviewAssignment->id,
                'dueAt' => $reviewAssignment->due_at,
                'respondedAt' => $reviewAssignment->responded_at,
                'declinedAt' => $reviewAssignment->declined_at,
                'completedAt' => $reviewAssignment->completed_at,
                'recommendation' => $reviewAssignment->recommendation?->value,
            ],
            'submission' => [
                'title' => $submission->currentPublication?->title?->get($locale, 'en') ?? "Submission #{$submission->id}",
                'abstract' => $submission->currentPublication?->abstract?->get($locale, 'en'),
            ],
            'manuscript' => $manuscript ? [
                'originalName' => $manuscript->original_name,
                'downloadUrl' => app(FileStorage::class)->temporaryUrl($manuscript->path, now()->addHours(2), $manuscript->disk),
            ] : null,
            'reviewForm' => $submission->section?->reviewForm ? [
                'title' => $submission->section->reviewForm->title->get($locale, 'en'),
                'elements' => $submission->section->reviewForm->elements->map(fn ($element) => [
                    'id' => $element->id,
                    'type' => $element->type->value,
                    'label' => $element->label->get($locale, 'en'),
                    'helpText' => $element->help_text?->get($locale, 'en'),
                    'isRequired' => $element->is_required,
                    'options' => $element->options,
                ]),
            ] : null,
            'recommendations' => collect(ReviewRecommendation::cases())
                ->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()]),
        ]);
    }

    public function store(Request $request, string $journal, ReviewAssignment $reviewAssignment): RedirectResponse
    {
        Gate::authorize('view', $reviewAssignment);

        if ($reviewAssignment->declined_at !== null) {
            abort(403, 'This invitation was declined.');
        }

        if ($reviewAssignment->responded_at === null) {
            abort(403, 'Accept the invitation before submitting a review.');
        }

        if ($reviewAssignment->completed_at !== null) {
            abort(422, 'This review has already been submitted.');
        }

        $data = $request->validate([
            'recommendation' => ['required', Rule::enum(ReviewRecommendation::class)],
            'answers' => ['nullable', 'array'],
        ]);

        try {
            $reviewAssignment->submitReview($data['answers'] ?? [], ReviewRecommendation::from($data['recommendation']));
        } catch (ReviewFormValidationException $e) {
            throw ValidationException::withMessages(['answers' => $e->getMessage()]);
        }

        return redirect()
            ->route('journal.reviews.show', ['journal' => $journal, 'reviewAssignment' => $reviewAssignment->id])
            ->with('success', 'Review submitted. Thank you.');
    }
}
