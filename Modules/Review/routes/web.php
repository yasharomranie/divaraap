<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Review\Http\Controllers\ReviewController;

Route::middleware(['web', 'auth'])->name('reviews.')->group(function (): void {
    Route::get('/panel/my-reviews', [ReviewController::class, 'index'])->name('mine');
    Route::post('/sellers/{seller}/reviews', [ReviewController::class, 'store'])
        ->where('seller', '[1-9][0-9]{0,17}')
        ->middleware('throttle:10,1')
        ->name('store');

    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->where('review', '[1-9][0-9]{0,17}')->middleware('throttle:20,1,review-delete:')->name('destroy');
});
