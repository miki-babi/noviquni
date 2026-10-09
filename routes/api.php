<?php

use App\Http\Controllers\Api\PaymentBotController;
use Illuminate\Support\Facades\Route;

Route::prefix('payment-bot')
    ->middleware(['payment-bot.secret', 'throttle:60,1'])
    ->group(function (): void {
        Route::get('quote', [PaymentBotController::class, 'quote'])->name('api.payment-bot.quote');
        Route::post('payments', [PaymentBotController::class, 'store'])->name('api.payment-bot.payments.store');
        Route::post('payments/{payment}/verify', [PaymentBotController::class, 'verify'])->name('api.payment-bot.payments.verify');
        Route::post('payments/{payment}/reject', [PaymentBotController::class, 'reject'])->name('api.payment-bot.payments.reject');
    });
