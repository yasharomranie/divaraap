<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\Listing\Http\Controllers\ListingChecklistController;
use Modules\Listing\Http\Controllers\ListingController;
use Modules\Listing\Http\Controllers\ListingLocationController;

Route::middleware('web')->prefix('listings')->name('listings.')->group(function () {
    Route::get('/', [ListingController::class, 'index'])->name('index');
    Route::get('/map', [ListingController::class, 'index'])->name('map');
    Route::middleware(['auth', 'throttle:30,1'])->group(function (): void {
        Route::get('/{listing}/location', [ListingLocationController::class, 'index'])->name('location');
        Route::post('/{listing}/location', [ListingLocationController::class, 'save'])->name('location.save');
        Route::delete('/{listing}/location', [ListingLocationController::class, 'remove'])->name('location.remove');
    });
    Route::get('/create', [ListingController::class, 'create'])->name('create');
    Route::post('/', [ListingController::class, 'store'])->name('store');
    Route::middleware(['auth', 'throttle:30,1'])->get('/{listing}/contact', [ListingController::class, 'contact'])->name('contact');
    Route::get('/{listing}/trade-checklist', [ListingChecklistController::class, 'index'])->name('checklist');
    Route::post('/{listing}/trade-checklist', [ListingChecklistController::class, 'save'])->middleware(['auth', 'throttle:30,1,trade-checklist:'])->name('checklist.save');
    Route::get('/{listing}', [ListingController::class, 'show'])->name('show');
});
