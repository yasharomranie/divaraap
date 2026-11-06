<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\EmailPreferenceController;
use Modules\Notification\Http\Controllers\NotificationController;

Route::middleware(['web', 'auth'])->prefix('panel')->name('panel.notifications.')->group(function (): void {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('read-all');
});

Route::middleware(['web', 'auth', 'throttle:20,1'])->group(function (): void {
    Route::get('/panel/notification-email', [EmailPreferenceController::class, 'edit'])->name('notification-email.edit');
    Route::post('/panel/notification-email', [EmailPreferenceController::class, 'update'])->name('notification-email.update');
});

Route::middleware(['web', 'throttle:30,1'])->prefix('notification-email/unsubscribe')->group(function (): void {
    Route::get('/{token}', [EmailPreferenceController::class, 'unsubscribe'])->where('token', '[A-Za-z0-9]{64}')->name('notification-email.unsubscribe');
    Route::post('/{token}', [EmailPreferenceController::class, 'stop'])->where('token', '[A-Za-z0-9]{64}')->name('notification-email.stop');
});
