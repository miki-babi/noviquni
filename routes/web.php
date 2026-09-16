<?php

use App\Http\Controllers\Public\CourseController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ResourceController;
use App\Http\Controllers\Public\ResourceHubController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\StreamController;
use App\Http\Controllers\Public\UniversityController;
use App\Http\Controllers\Telegram\MiniApp\BrowseController;
use App\Http\Controllers\Telegram\MiniApp\ContinueController;
use App\Http\Controllers\Telegram\MiniApp\PremiumController;
use App\Http\Controllers\Telegram\MiniApp\ProfileController;
use App\Http\Controllers\Telegram\MiniApp\SessionController;
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

Route::prefix('tg')->name('tg.')->group(function () {
    Route::get('session', [SessionController::class, 'create'])
        ->name('session.create');
    Route::post('session', [SessionController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('session.store');

    Route::middleware(['telegram.miniapp', 'throttle:60,1'])->group(function () {
        Route::get('/', App\Http\Controllers\Telegram\MiniApp\HomeController::class)->name('home');
        Route::get('continue', [ContinueController::class, 'show'])->name('continue');
        Route::get('browse', [BrowseController::class, 'show'])->name('browse');
        Route::get('courses/{course:slug}', [App\Http\Controllers\Telegram\MiniApp\CourseController::class, 'show'])->name('courses.show');
        Route::get('courses/{course:slug}/{hub}', [App\Http\Controllers\Telegram\MiniApp\CourseController::class, 'hub'])
            ->whereIn('hub', ['notes', 'modules', 'practice'])
            ->name('courses.hub');
        Route::get('resources/{resource:slug}', [App\Http\Controllers\Telegram\MiniApp\ResourceController::class, 'show'])->name('resources.show');
        Route::get('profile', [ProfileController::class, 'show'])->name('profile');
        Route::post('profile/notifications', [ProfileController::class, 'toggleNotifications'])->name('profile.notifications');
        Route::post('profile/notifications/enable', [ProfileController::class, 'enableNotifications'])->name('profile.notifications.enable');
        Route::post('profile/locale', [ProfileController::class, 'updateLocale'])->name('profile.locale');
        Route::get('premium', [PremiumController::class, 'show'])->name('premium');
        Route::post('premium/pay', [PremiumController::class, 'pay'])->name('premium.pay');
    });
});

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
