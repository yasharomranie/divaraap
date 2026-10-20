<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Offer\Http\Controllers\ConversationOfferController;
use Modules\Offer\Http\Controllers\OfferController;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/panel/offers', [OfferController::class, 'index'])->name('panel.offers.index');

    Route::prefix('conversations/{conversation}/offers')->where(['conversation' => '[1-9][0-9]{0,17}', 'offer' => '[1-9][0-9]{0,17}'])->name('conversation-offers.')->group(function (): void {
        Route::get('/', [ConversationOfferController::class, 'snapshot'])->middleware('throttle:30,1')->name('snapshot');
        Route::post('/', [ConversationOfferController::class, 'store'])->middleware('throttle:20,1,chat-offers:')->name('store');
        Route::post('/{offer}', [ConversationOfferController::class, 'respond'])->middleware('throttle:20,1,chat-offers:')->name('respond');
    });

    Route::name('offers.')->group(function (): void {
        Route::post('/listings/{listing}/offers', [OfferController::class, 'store'])
            ->whereNumber('listing')
            ->middleware('throttle:20,1')
            ->name('store');

        Route::post('/offers/{offer}/accept', [OfferController::class, 'accept'])->name('accept');
        Route::post('/offers/{offer}/decline', [OfferController::class, 'decline'])->name('decline');
        Route::post('/offers/{offer}/withdraw', [OfferController::class, 'withdraw'])->name('withdraw');
    });
});
