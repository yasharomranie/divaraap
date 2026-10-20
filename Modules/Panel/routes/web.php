<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Panel\App\Http\Controllers\ListingAvailabilityController;
use Modules\Panel\App\Http\Controllers\ListingDraftPhotoController;
use Modules\Panel\App\Http\Controllers\ListingDuplicateController;
use Modules\Panel\App\Http\Controllers\ListingImprovementController;
use Modules\Panel\App\Http\Controllers\ListingPerformanceController;
use Modules\Panel\App\Http\Controllers\ListingTextSuggestionController;
use Modules\Panel\App\Http\Controllers\PanelController;

Route::middleware(['web', 'auth'])->prefix('panel')->name('panel.')->group(function () {
    Route::get('/', [PanelController::class, 'index'])->name('index');
    Route::get('/my-listings', [PanelController::class, 'listings'])->name('listings.index');
    Route::post('/listing-text-suggestions', [ListingTextSuggestionController::class, 'generate'])->middleware('throttle:20,1')->name('listings.text-suggestions');
    Route::get('/drafts/{draft}/photos/{file}', ListingDraftPhotoController::class)->where('draft', '[1-9][0-9]{0,17}')->whereUuid('file')->name('drafts.photo');
    Route::get('/create-listing', [PanelController::class, 'create'])->name('listings.create');
    Route::get('/my-listings/{listing}/duplicate', [ListingDuplicateController::class, 'show'])->where('listing', '[1-9][0-9]{0,17}')->name('listings.duplicate');
    Route::post('/my-listings/{listing}/duplicate', [ListingDuplicateController::class, 'store'])->where('listing', '[1-9][0-9]{0,17}')->middleware('throttle:10,1,listing-duplicate:')->name('listings.duplicate.store');
    Route::get('/my-listings/{listing}/improve', [ListingImprovementController::class, 'show'])->name('listings.improve');
    Route::post('/my-listings/{listing}/photos', [ListingImprovementController::class, 'photo'])->middleware('throttle:20,1')->name('listings.photos.store');
    Route::get('/my-listings/{listing}/performance', [ListingPerformanceController::class, 'show'])->name('listings.performance');
    Route::get('/my-listings/{listing}/edit', [PanelController::class, 'editListing'])->name('listings.edit');
    Route::put('/my-listings/{listing}', [PanelController::class, 'updateListing'])->name('listings.update');
    Route::post('/my-listings/{listing}/remove', [PanelController::class, 'destroyListing'])->name('listings.destroy');
    Route::post('/my-listings/{listing}/pause', [ListingAvailabilityController::class, 'pause'])->where('listing', '[1-9][0-9]{0,17}')->middleware('throttle:30,1')->name('listings.pause');
    Route::post('/my-listings/{listing}/resume', [ListingAvailabilityController::class, 'resume'])->where('listing', '[1-9][0-9]{0,17}')->middleware('throttle:30,1')->name('listings.resume');
    Route::post('/my-listings/{listing}/mark-sold', [PanelController::class, 'markListingAsSold'])->name('listings.mark-sold');
    Route::post('/my-listings/{listing}/republish', [PanelController::class, 'republishListing'])->name('listings.republish');
    Route::get('/videos', [PanelController::class, 'videos'])->name('videos.index');
    Route::post('/videos', [PanelController::class, 'storeVideo'])->name('videos.store');
    Route::get('/videos/{video}/edit', [PanelController::class, 'editVideo'])->name('videos.edit');
    Route::put('/videos/{video}', [PanelController::class, 'updateVideo'])->name('videos.update');
    Route::delete('/videos/{video}', [PanelController::class, 'destroyVideo'])->name('videos.destroy');
    Route::get('/my-profile', [PanelController::class, 'profile'])->name('profile.edit');
});
