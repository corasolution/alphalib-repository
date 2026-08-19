<?php

use Alpha\Journal\Http\Controllers\OrcidController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('orcid')->name('orcid.')->group(function () {
    Route::get('redirect', [OrcidController::class, 'redirect'])->name('redirect');
    Route::get('callback', [OrcidController::class, 'callback'])->name('callback');
});
