<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Favorite\App\Http\Controllers\CollectionController;
use Modules\Favorite\App\Http\Controllers\ComparisonController;
use Modules\Favorite\App\Http\Controllers\ComparisonDossierController;
use Modules\Favorite\App\Http\Controllers\FavoriteController;
use Modules\Favorite\App\Http\Controllers\HistoryController;
use Modules\Favorite\App\Http\Controllers\RareWatchController;
use Modules\Favorite\App\Http\Controllers\ShortlistSharingController;

Route::middleware('web')->group(function () {
    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');
    Route::post('/history/preferences', [HistoryController::class, 'configure'])->middleware('throttle:30,1')->name('history.configure');
    Route::delete('/history/{listing}', [HistoryController::class, 'remove'])->whereNumber('listing')->name('history.remove');
    Route::delete('/history', [HistoryController::class, 'clear'])->name('history.clear');

    Route::get('/compare', [ComparisonController::class, 'index'])->name('comparison.index');
    Route::post('/compare/{listing}', [ComparisonController::class, 'add'])->middleware('throttle:60,1')->name('comparison.add');
    Route::delete('/compare/{listing}', [ComparisonController::class, 'remove'])->whereNumber('listing')->name('comparison.remove');
    Route::delete('/compare', [ComparisonController::class, 'clear'])->name('comparison.clear');

    Route::prefix('favorites')->name('favorites.')->group(function () {
        Route::get('/', [FavoriteController::class, 'index'])->name('index');
    });

    Route::middleware('auth')->prefix('collections')->name('collections.')->group(function (): void {
        Route::get('/', [CollectionController::class, 'index'])->name('index');
        Route::post('/', [CollectionController::class, 'save'])->middleware('throttle:30,1')->name('store');
        Route::put('/{collection}', [CollectionController::class, 'save'])->whereNumber('collection')->name('update');
        Route::delete('/{collection}', [CollectionController::class, 'remove'])->whereNumber('collection')->name('destroy');
        Route::post('/listings/{listing}', [CollectionController::class, 'move'])->whereNumber('listing')->middleware('throttle:60,1')->name('move');
    });

    Route::middleware('auth')->prefix('favorites')->name('favorites.')->group(function () {
        Route::get('/alerts', [FavoriteController::class, 'alerts'])->name('alerts');
        Route::post('/alerts', [FavoriteController::class, 'configureAlert'])->middleware('throttle:30,1')->name('alerts.configure');
        Route::post('/listings/{listing}/toggle', [FavoriteController::class, 'toggleListing'])->name('listings.toggle');
        Route::post('/sellers/{seller}/toggle', [FavoriteController::class, 'toggleSeller'])->name('sellers.toggle');
        Route::post('/searches', [FavoriteController::class, 'storeSearch'])->name('searches.store');
        Route::delete('/searches/{favoriteSearch}', [FavoriteController::class, 'destroySearch'])->name('searches.destroy');
    });
});

Route::middleware(['web', 'auth', 'throttle:60,1'])->prefix('panel/rare-watches')->name('rare-watches.')->group(function (): void {
    Route::get('/', [RareWatchController::class, 'index'])->name('index');
    Route::get('/create', [RareWatchController::class, 'create'])->name('create');
    Route::post('/', [RareWatchController::class, 'store'])->middleware('throttle:10,60,rare-create:')->name('store');
    Route::post('/{watch}', [RareWatchController::class, 'change'])->where('watch', '[1-9][0-9]{0,17}')->middleware('throttle:30,1,rare-change:')->name('change');
});

Route::middleware(['web', 'throttle:60,1'])->group(function (): void {
    Route::get('/shared-comparisons/{token}', [ComparisonDossierController::class, 'shared'])->where('token', '[a-f0-9]{64}')->name('dossiers.shared');
    Route::middleware('auth')->prefix('panel/comparison-dossiers')->name('dossiers.')->group(function (): void {
        Route::get('/', [ComparisonDossierController::class, 'index'])->name('index');
        Route::get('/create', [ComparisonDossierController::class, 'create'])->name('create');
        Route::post('/', [ComparisonDossierController::class, 'store'])->middleware('throttle:10,60,dossier-create:')->name('store');
        Route::post('/{dossier}', [ComparisonDossierController::class, 'change'])->where('dossier', '[1-9][0-9]{0,17}')->middleware('throttle:30,1,dossier-change:')->name('change');
    });
});

Route::middleware(['web', 'throttle:60,1'])->group(function (): void {
    Route::get('/shared-shortlists/{token}', [ShortlistSharingController::class, 'shared'])->where('token', '[a-f0-9]{64}')->name('shortlists.shared');
    Route::middleware('auth')->group(function (): void {
        Route::get('/collections/{collection}/sharing', [ShortlistSharingController::class, 'manage'])->where('collection', '[1-9][0-9]{0,17}')->name('shortlists.manage');
        Route::post('/collections/{collection}/sharing', [ShortlistSharingController::class, 'change'])->where('collection', '[1-9][0-9]{0,17}')->middleware('throttle:20,1,shortlist-sharing:')->name('shortlists.change');
    });
});
