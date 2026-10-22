<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\Listing\Http\Controllers\ListingChecklistController;
use Modules\Listing\Http\Controllers\ListingController;
use Modules\Listing\Http\Controllers\ListingLocationController;
use Modules\Listing\Http\Controllers\WantedRequestController;

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

Route::middleware(['web', 'throttle:60,1'])->name('wanted.')->group(function (): void {
    Route::get('/wanted-requests', [WantedRequestController::class, 'index'])->name('index');
    Route::get('/wanted-requests/{wanted}', [WantedRequestController::class, 'show'])->where('wanted', '[1-9][0-9]{0,17}')->name('show');
    Route::middleware('auth')->prefix('panel/wanted-requests')->group(function (): void {
        Route::get('/', [WantedRequestController::class, 'mine'])->name('mine');
        Route::get('/create', [WantedRequestController::class, 'create'])->name('create');
        Route::post('/', [WantedRequestController::class, 'store'])->middleware('throttle:10,60,wanted-create:')->name('store');
        Route::get('/{wanted}', [WantedRequestController::class, 'own'])->where('wanted', '[1-9][0-9]{0,17}')->name('own');
        Route::get('/{wanted}/edit', [WantedRequestController::class, 'edit'])->where('wanted', '[1-9][0-9]{0,17}')->name('edit');
        Route::put('/{wanted}', [WantedRequestController::class, 'update'])->where('wanted', '[1-9][0-9]{0,17}')->middleware('throttle:20,60,wanted-edit:')->name('update');
        Route::post('/{wanted}/close', [WantedRequestController::class, 'close'])->where('wanted', '[1-9][0-9]{0,17}')->name('close');
    });
    Route::post('/wanted-requests/{wanted}/suggest', [WantedRequestController::class, 'suggest'])->where('wanted', '[1-9][0-9]{0,17}')->middleware(['auth', 'throttle:20,60,wanted-suggest:'])->name('suggest');
});

Route::middleware(['web', 'throttle:60,1'])->name('service-requests.')->group(function (): void {
    Route::get('/local-services', [WantedRequestController::class, 'index'])->name('index');
    Route::get('/local-services/{wanted}', [WantedRequestController::class, 'show'])->where('wanted', '[1-9][0-9]{0,17}')->name('show');
    Route::middleware('auth')->prefix('panel/service-requests')->group(function (): void {
        Route::get('/', [WantedRequestController::class, 'mine'])->name('mine');
        Route::get('/create', [WantedRequestController::class, 'create'])->name('create');
        Route::post('/', [WantedRequestController::class, 'store'])->middleware('throttle:10,60,wanted-create:')->name('store');
        Route::get('/{wanted}', [WantedRequestController::class, 'own'])->where('wanted', '[1-9][0-9]{0,17}')->name('own');
        Route::get('/{wanted}/edit', [WantedRequestController::class, 'edit'])->where('wanted', '[1-9][0-9]{0,17}')->name('edit');
        Route::put('/{wanted}', [WantedRequestController::class, 'update'])->where('wanted', '[1-9][0-9]{0,17}')->middleware('throttle:20,60,wanted-edit:')->name('update');
        Route::post('/{wanted}/close', [WantedRequestController::class, 'close'])->where('wanted', '[1-9][0-9]{0,17}')->name('close');
    });
    Route::post('/local-services/{wanted}/suggest', [WantedRequestController::class, 'suggest'])->where('wanted', '[1-9][0-9]{0,17}')->middleware(['auth', 'throttle:20,60,wanted-suggest:'])->name('suggest');
});
