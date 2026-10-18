<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Advertising\Http\Controllers\AdvertisingEventController;
use Modules\Advertising\Http\Controllers\AdvertisingReportController;
use Modules\Advertising\Http\Controllers\BannerPreviewController;

Route::middleware(['web', 'throttle:120,1'])->post('/advertising/events', AdvertisingEventController::class)->name('advertising.events');

Route::middleware(['web', 'auth'])->get('/admin/advertising-render/{banner}', BannerPreviewController::class)->whereNumber('banner')->name('advertising.preview-render');

Route::middleware(['web', 'auth', 'throttle:20,1'])->get('/admin/advertising-report.csv', AdvertisingReportController::class)->name('advertising.report-download');
