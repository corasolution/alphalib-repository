<?php

use Alpha\Journal\Http\Controllers\RorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('ror')->name('ror.')->group(function () {
    Route::get('search', [RorController::class, 'search'])->name('search');
});
