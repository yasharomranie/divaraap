<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Advertising\Http\Controllers\AdvertisingEventController;
use Modules\Advertising\Http\Controllers\AdvertisingInvoiceController;
use Modules\Advertising\Http\Controllers\AdvertisingOrderController;
use Modules\Advertising\Http\Controllers\AdvertisingReportController;
use Modules\Advertising\Http\Controllers\BannerPreviewController;
use Modules\Advertising\Http\Controllers\VideoCaptionController;

Route::middleware(['web', 'throttle:120,1'])->post('/advertising/events', AdvertisingEventController::class)->name('advertising.events');

Route::middleware(['web', 'auth'])->get('/admin/advertising-render/{banner}', BannerPreviewController::class)->whereNumber('banner')->name('advertising.preview-render');

Route::middleware(['web', 'auth', 'throttle:20,1'])->get('/admin/advertising-report.csv', AdvertisingReportController::class)->name('advertising.report-download');

Route::get('/advertising/video-captions/{banner}', VideoCaptionController::class)->where('banner', '[1-9][0-9]{0,17}')->name('advertising.video-captions');

Route::middleware(['web', 'auth', 'throttle:60,1'])->group(function (): void {
    Route::get('/panel/advertising-invoices', [AdvertisingInvoiceController::class, 'index'])->name('advertising.invoices.index');
    Route::get('/panel/advertising-invoices/{invoice}', [AdvertisingInvoiceController::class, 'show'])->where('invoice', '[1-9][0-9]{0,17}')->name('advertising.invoices.show');
});

Route::middleware(['web', 'auth', 'throttle:60,1'])->prefix('panel/advertising-orders')->name('advertising.orders.')->group(function (): void {
    Route::get('/', [AdvertisingOrderController::class, 'index'])->name('index');
    Route::get('/create', [AdvertisingOrderController::class, 'create'])->name('create');
    Route::post('/', [AdvertisingOrderController::class, 'store'])->middleware('throttle:10,60')->name('store');
    Route::get('/{order}', [AdvertisingOrderController::class, 'show'])->where('order', '[1-9][0-9]{0,17}')->name('show');
    Route::post('/{order}/accept', [AdvertisingOrderController::class, 'accept'])->where('order', '[1-9][0-9]{0,17}')->name('accept');
    Route::post('/{order}/cancel', [AdvertisingOrderController::class, 'cancel'])->where('order', '[1-9][0-9]{0,17}')->name('cancel');
});
