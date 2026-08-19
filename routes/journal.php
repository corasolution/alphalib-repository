<?php

use Alpha\Journal\Http\Controllers\Journal\DashboardController;
use Alpha\Journal\Http\Controllers\Journal\ExportController;
use Alpha\Journal\Http\Controllers\Journal\InvitationController;
use Alpha\Journal\Http\Controllers\Journal\ReviewInvitationController;
use Alpha\Journal\Http\Controllers\Journal\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'journal'])->prefix('journal/{journal}')->name('journal.')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');

    Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::get('invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

    Route::get('export/articles.csv', [ExportController::class, 'articlesCsv'])->name('export.articles-csv');
});

// No `auth` here on purpose — signed URL only (CLAUDE.md §13.7: one-tap accept/decline, no login).
Route::middleware(['signed'])->prefix('journal/{journal}')->name('journal.')->group(function () {
    Route::get('reviews/{reviewAssignment}/accept', [ReviewInvitationController::class, 'accept'])->name('reviews.accept');
    Route::get('reviews/{reviewAssignment}/decline', [ReviewInvitationController::class, 'decline'])->name('reviews.decline');
});
