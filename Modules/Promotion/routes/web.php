<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Promotion\Http\Controllers\PromotionController;
use Modules\Promotion\Http\Controllers\SellerPackageController;

Route::middleware('web')->group(function (): void {
    Route::get('/promotions', [PromotionController::class, 'plans'])->name('promotions.plans');

    Route::middleware('auth')->prefix('panel')->name('panel.promotions.')->group(function (): void {
        Route::get('/promotions', [PromotionController::class, 'index'])->name('index');
        Route::post('/promotions', [PromotionController::class, 'store'])->name('store');
    });
});

Route::middleware(['web', 'auth', 'throttle:30,1'])->prefix('panel/seller-packages')->name('seller-packages.')->group(function (): void {
    Route::get('/', [SellerPackageController::class, 'index'])->name('index');
    Route::post('/request/{package}', [SellerPackageController::class, 'request'])->where('package', '[1-9][0-9]{0,17}')->middleware('throttle:10,60')->name('request');
    Route::get('/{purchase}', [SellerPackageController::class, 'show'])->where('purchase', '[1-9][0-9]{0,17}')->name('show');
    Route::post('/{purchase}/redeem', [SellerPackageController::class, 'redeem'])->where('purchase', '[1-9][0-9]{0,17}')->name('redeem');
});
