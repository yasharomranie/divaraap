<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Advertising\Http\Controllers\AdvertisingEventController;

Route::middleware(['web', 'throttle:120,1'])->post('/advertising/events', AdvertisingEventController::class)->name('advertising.events');
