<?php

use Alpha\Journal\Http\Controllers\DashboardController;
use Alpha\Journal\Http\Controllers\OaiController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// OAI-PMH 2.0 provider (CLAUDE.md §5.1) — site-wide, spans every enabled journal via sets.
Route::match(['get', 'post'], '/oai', [OaiController::class, 'handle'])->name('oai');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/journal.php';
require __DIR__.'/orcid.php';
require __DIR__.'/ror.php';
