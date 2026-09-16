<?php

use App\Http\Controllers\Public\CourseController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ResourceController;
use App\Http\Controllers\Public\ResourceHubController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\StreamController;
use App\Http\Controllers\Public\UniversityController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/universities', [UniversityController::class, 'index'])->name('universities.index');
Route::get('/universities/{university:slug}', [UniversityController::class, 'show'])->name('universities.show');

Route::get('/streams/{stream:slug}', [StreamController::class, 'show'])->name('streams.show');

Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{course:slug}', [CourseController::class, 'show'])->name('courses.show');

Route::get('/resources', [ResourceController::class, 'index'])->name('resources.index');
Route::get('/resources/{resource:slug}', [ResourceController::class, 'show'])->name('resources.show');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/{hub}', [ResourceHubController::class, 'show'])
    ->whereIn('hub', ['modules', 'notes', 'exams', 'practice'])
    ->name('hubs.show');

Route::get('/{hub}/{course:slug}', [ResourceHubController::class, 'show'])
    ->whereIn('hub', ['modules', 'notes', 'exams', 'practice'])
    ->name('hubs.course');
Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware(['throttle:60,1'])
    ->name('telegram.webhook');

Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::get('/telegram/webhook-status', [TelegramWebhookController::class, 'status'])
        ->name('telegram.webhook-status');

    Route::post('/telegram/webhook-status', [TelegramWebhookController::class, 'set'])
        ->name('telegram.webhook-set');
});
