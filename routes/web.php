<?php

use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware(['throttle:60,1'])
    ->name('telegram.webhook');

Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::get('/telegram/webhook-status', [TelegramWebhookController::class, 'status'])
        ->name('telegram.webhook-status');

    Route::post('/telegram/webhook-status', [TelegramWebhookController::class, 'set'])
        ->name('telegram.webhook-set');
});
