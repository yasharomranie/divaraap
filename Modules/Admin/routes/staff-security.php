<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\StaffSecurityController;

Route::middleware(['web', 'auth', 'throttle:20,1'])->prefix('staff-security')->name('staff-security.')->group(function (): void {
    Route::get('/setup', [StaffSecurityController::class, 'setup'])->name('setup');
    Route::post('/setup', [StaffSecurityController::class, 'begin'])->name('begin');
    Route::post('/enroll', [StaffSecurityController::class, 'enroll'])->name('enroll');
    Route::get('/challenge', [StaffSecurityController::class, 'challenge'])->name('challenge');
    Route::post('/challenge', [StaffSecurityController::class, 'verify'])->name('verify');
    Route::get('/recovery', [StaffSecurityController::class, 'recovery'])->name('recovery');
});
