<?php

use Alpha\Journal\Http\Controllers\Journal\DashboardController;
use Alpha\Journal\Http\Controllers\Journal\DecisionController;
use Alpha\Journal\Http\Controllers\Journal\ExportController;
use Alpha\Journal\Http\Controllers\Journal\InvitationController;
use Alpha\Journal\Http\Controllers\Journal\IssueAssignmentController;
use Alpha\Journal\Http\Controllers\Journal\IssueGalleyController;
use Alpha\Journal\Http\Controllers\Journal\IssuesController;
use Alpha\Journal\Http\Controllers\Journal\JournalSettingsController;
use Alpha\Journal\Http\Controllers\Journal\PeopleController;
use Alpha\Journal\Http\Controllers\Journal\PublicArticleController;
use Alpha\Journal\Http\Controllers\Journal\PublicationDoiController;
use Alpha\Journal\Http\Controllers\Journal\PlagiarismCheckController;
use Alpha\Journal\Http\Controllers\Journal\PublicationGalleyController;
use Alpha\Journal\Http\Controllers\Journal\PublicJournalController;
use Alpha\Journal\Http\Controllers\Journal\ReviewAssignmentController;
use Alpha\Journal\Http\Controllers\Journal\ReviewInvitationController;
use Alpha\Journal\Http\Controllers\Journal\ReviewSubmissionController;
use Alpha\Journal\Http\Controllers\Journal\StageAssignmentController;
use Alpha\Journal\Http\Controllers\Journal\SubmissionAnalysisController;
use Alpha\Journal\Http\Controllers\Journal\SubmissionController;
use Alpha\Journal\Http\Controllers\Journal\SubmissionFilesController;
use Alpha\Journal\Http\Controllers\Journal\SubmissionsCreateController;
use Alpha\Journal\Http\Controllers\Journal\SubmissionsIndexController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'journal'])->prefix('journal/{journal}')->name('journal.')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('submissions', [SubmissionsIndexController::class, 'index'])->name('submissions.index');
    Route::get('submissions/create', [SubmissionsCreateController::class, 'create'])->name('submissions.create');

    // A1 metadata extraction (§6). Throttled because each call spends real money on a model —
    // without it, one author holding down a button is an unbounded bill.
    Route::post('submissions/analyze', SubmissionAnalysisController::class)
        ->middleware('throttle:20,1')
        ->name('submissions.analyze');
    Route::post('submissions/analyze/feedback', [SubmissionAnalysisController::class, 'feedback'])
        ->name('submissions.analyze.feedback');

    Route::post('submissions', [SubmissionsCreateController::class, 'store'])->name('submissions.store');
    Route::get('submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
    Route::post('submissions/{submission}/decisions', [DecisionController::class, 'store'])->name('submissions.decisions.store');
    // Editorial responsibility for a submission — what guardStageHasEditor() checks before
    // allowing any decision. Nothing created these until now, so no submission could progress.
    Route::post('submissions/{submission}/editors', [StageAssignmentController::class, 'store'])->name('submissions.editors.store');
    Route::delete('submissions/{submission}/editors/{stageAssignment}', [StageAssignmentController::class, 'destroy'])->name('submissions.editors.destroy');

    Route::post('submissions/{submission}/review-assignments', [ReviewAssignmentController::class, 'store'])->name('submissions.review-assignments.store');
    Route::post('submissions/{submission}/files', [SubmissionFilesController::class, 'store'])->name('submissions.files.store');
    Route::post('submissions/{submission}/issue-assignment', [IssueAssignmentController::class, 'store'])->name('submissions.issue-assignment.store');

    // Article galleys — the readable file a reader downloads (§7), produced during Production.
    // Similarity screening: recorded by an editor from whatever tool the institution licences.
    Route::post('submissions/{submission}/plagiarism-checks', [PlagiarismCheckController::class, 'store'])->name('submissions.plagiarism.store');
    Route::get('submissions/{submission}/plagiarism-checks/{plagiarismCheck}/report', [PlagiarismCheckController::class, 'report'])->name('submissions.plagiarism.report');

    Route::post('submissions/{submission}/galleys', [PublicationGalleyController::class, 'store'])->name('submissions.galleys.store');
    Route::delete('submissions/{submission}/galleys/{publicationGalley}', [PublicationGalleyController::class, 'destroy'])->name('submissions.galleys.destroy');

    Route::get('reviews/{reviewAssignment}', [ReviewSubmissionController::class, 'show'])->name('reviews.show');
    Route::post('reviews/{reviewAssignment}', [ReviewSubmissionController::class, 'store'])->name('reviews.store');

    Route::get('issues', [IssuesController::class, 'index'])->name('issues.index');
    Route::get('issues/create', [IssuesController::class, 'create'])->name('issues.create');
    Route::post('issues', [IssuesController::class, 'store'])->name('issues.store');
    Route::get('issues/{issue}', [IssuesController::class, 'show'])->name('issues.show');
    Route::post('issues/{issue}/publish', [IssuesController::class, 'publish'])->name('issues.publish');
    Route::post('issues/{issue}/galleys', [IssueGalleyController::class, 'store'])->name('issues.galleys.store');

    Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::get('invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

    // Journal identity as a publication: publisher, ISSNs, DOI prefix and pattern. Every one of
    // these was already read by the DOAJ/Crossref/DataCite integrations and had nowhere to be set.
    Route::get('settings', [JournalSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [JournalSettingsController::class, 'update'])->name('settings.update');

    // The DOI on an article, assigned in Production alongside the issue and galley.
    Route::put('submissions/{submission}/doi', [PublicationDoiController::class, 'update'])->name('submissions.doi.update');
    Route::post('submissions/{submission}/doi/generate', [PublicationDoiController::class, 'generate'])->name('submissions.doi.generate');

    Route::get('people', [PeopleController::class, 'index'])->name('people.index');
    Route::post('people', [PeopleController::class, 'store'])->name('people.store');
    Route::post('people/invite', [PeopleController::class, 'invite'])->name('people.invite');
    Route::post('people/accounts', [PeopleController::class, 'storeAccount'])->name('people.accounts.store');
    Route::delete('people/{user}/{role}', [PeopleController::class, 'destroy'])->name('people.destroy');

    Route::get('export/articles.csv', [ExportController::class, 'articlesCsv'])->name('export.articles-csv');
});

// No `auth` here on purpose — signed URL only (CLAUDE.md §13.7: one-tap accept/decline, no login).
Route::middleware(['signed'])->prefix('journal/{journal}')->name('journal.')->group(function () {
    Route::get('reviews/{reviewAssignment}/accept', [ReviewInvitationController::class, 'accept'])->name('reviews.accept');
    Route::get('reviews/{reviewAssignment}/decline', [ReviewInvitationController::class, 'decline'])->name('reviews.decline');
});

// No `auth` here on purpose — the public journal homepage. `journal` alone (no `auth`) is proven
// safe by the signed-URL group above; ResolveJournalContext already 404s a disabled/nonexistent
// journal before this ever runs.
Route::middleware(['journal'])->prefix('journal/{journal}')->name('journal.')->group(function () {
    Route::get('/', [PublicJournalController::class, 'show'])->name('public.show');

    // The article landing page and its downloads (§7). Public by design — this is the thing an
    // indexer crawls and a reader cites.
    Route::get('article/{publication}', [PublicArticleController::class, 'show'])->name('public.article');
    Route::get('article/{publication}/galley/{publicationGalley}', [PublicArticleController::class, 'galley'])->name('public.article.galley');
    Route::get('article/{publication}/cite/{format}', [PublicArticleController::class, 'citation'])->name('public.article.cite');
});
