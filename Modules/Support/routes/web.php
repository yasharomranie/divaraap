<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Support\Http\Controllers\SupportController;

Route::middleware(['web', 'auth'])->prefix('panel/support')->name('support.')->group(function (): void {
    Route::get('/', [SupportController::class, 'index'])->name('index');
    Route::get('/create', [SupportController::class, 'create'])->name('create');
    Route::post('/', [SupportController::class, 'store'])->middleware('throttle:10,60')->name('store');
    Route::get('/{ticket}', [SupportController::class, 'show'])->where('ticket', '[1-9][0-9]{0,17}')->name('show');
    Route::post('/{ticket}', [SupportController::class, 'reply'])->where('ticket', '[1-9][0-9]{0,17}')->middleware('throttle:30,60')->name('reply');
});
