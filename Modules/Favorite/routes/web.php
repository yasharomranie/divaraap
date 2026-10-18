<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Favorite\App\Http\Controllers\ComparisonController;
use Modules\Favorite\App\Http\Controllers\FavoriteController;

Route::middleware('web')->group(function () {
    Route::get('/compare', [ComparisonController::class, 'index'])->name('comparison.index');
    Route::post('/compare/{listing}', [ComparisonController::class, 'add'])->middleware('throttle:60,1')->name('comparison.add');
    Route::delete('/compare/{listing}', [ComparisonController::class, 'remove'])->whereNumber('listing')->name('comparison.remove');
    Route::delete('/compare', [ComparisonController::class, 'clear'])->name('comparison.clear');

    Route::prefix('favorites')->name('favorites.')->group(function () {
        Route::get('/', [FavoriteController::class, 'index'])->name('index');
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
