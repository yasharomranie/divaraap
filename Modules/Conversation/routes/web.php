<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Conversation\App\Http\Controllers\ConversationController;
use Modules\Conversation\App\Http\Controllers\ConversationSafetyController;
use Modules\Conversation\App\Http\Controllers\SavedReplyController;

Route::middleware('web')->group(function () {
    Route::middleware('auth')->prefix('panel')->name('panel.')->group(function () {
        Route::get('/saved-replies', [SavedReplyController::class, 'index'])->name('saved-replies.index');
        Route::post('/saved-replies', [SavedReplyController::class, 'store'])->middleware('throttle:30,1')->name('saved-replies.store');
        Route::put('/saved-replies/{reply}', [SavedReplyController::class, 'update'])->where('reply', '[1-9][0-9]{0,17}')->middleware('throttle:30,1')->name('saved-replies.update');
        Route::delete('/saved-replies/{reply}', [SavedReplyController::class, 'destroy'])->where('reply', '[1-9][0-9]{0,17}')->middleware('throttle:30,1')->name('saved-replies.destroy');
        Route::get('/blocked-users', [ConversationSafetyController::class, 'blocked'])->name('blocked-users');
        Route::post('/blocked-users/{target}/unblock', [ConversationSafetyController::class, 'unblock'])->where('target', '[1-9][0-9]{0,17}')->middleware('throttle:20,1,chat-safety:')->name('blocked-users.unblock');
        Route::get('/inbox', [ConversationController::class, 'inbox'])->name('inbox.index');
    });

    Route::middleware('auth')->name('conversations.')->group(function () {
        Route::get('/conversations/{conversation}/safety', [ConversationSafetyController::class, 'show'])->where('conversation', '[1-9][0-9]{0,17}')->name('safety');
        Route::post('/conversations/{conversation}/block', [ConversationSafetyController::class, 'block'])->where('conversation', '[1-9][0-9]{0,17}')->middleware('throttle:20,1,chat-safety:')->name('block');
        Route::post('/conversations/{conversation}/report', [ConversationSafetyController::class, 'report'])->where('conversation', '[1-9][0-9]{0,17}')->middleware('throttle:5,1,chat-report:')->name('report');
        Route::get('/inbox/updates', [ConversationController::class, 'updates'])->middleware('throttle:30,1')->name('updates');
        Route::post('/listings/{listing}/conversation', [ConversationController::class, 'start'])->name('start');
        Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'send'])->name('messages.send');
        Route::post('/conversations/{conversation}/read', [ConversationController::class, 'read'])->name('read');
    });
});
