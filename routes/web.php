<?php

use App\Enums\ResourceHub;
use App\Http\Controllers\PaymentBotWebhookController;
use App\Http\Controllers\Public\CourseController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ResourceController;
use App\Http\Controllers\Public\ResourceHubController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\StreamController;
use App\Http\Controllers\Public\UniversityController;
use App\Http\Controllers\Telegram\MiniApp\HomeScreenController;
use App\Http\Controllers\Telegram\MiniApp\OpportunityController;
use App\Http\Controllers\Telegram\MiniApp\Players\DailyChallengeLeaderboardController;
use App\Http\Controllers\Telegram\MiniApp\Players\DailyChallengePlayerController;
use App\Http\Controllers\Telegram\MiniApp\Players\DailyChallengeResultController;
use App\Http\Controllers\Telegram\MiniApp\Players\ExamPlayerController;
use App\Http\Controllers\Telegram\MiniApp\Players\ExamResultController;
use App\Http\Controllers\Telegram\MiniApp\Players\QuestionReportController;
use App\Http\Controllers\Telegram\MiniApp\Players\QuizPlayerController;
use App\Http\Controllers\Telegram\MiniApp\Players\QuizResultController;
use App\Http\Controllers\Telegram\MiniApp\Players\ReferenceBooksPlayerController;
use App\Http\Controllers\Telegram\MiniApp\PremiumController;
use App\Http\Controllers\Telegram\MiniApp\ProfileController;
use App\Http\Controllers\Telegram\MiniApp\ProgressController;
use App\Http\Controllers\Telegram\MiniApp\ReferralShareController;
use App\Http\Controllers\Telegram\MiniApp\ResourceFileController;
use App\Http\Controllers\Telegram\MiniApp\SavedController;
use App\Http\Controllers\Telegram\MiniApp\SessionController;
use App\Http\Controllers\Telegram\MiniApp\UseBotController;
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
    Route::post('session/diagnose', [SessionController::class, 'diagnose'])
        ->middleware('throttle:60,1')
        ->name('session.diagnose');

    Route::middleware(['telegram.miniapp', 'throttle:60,1'])->group(function () {
        // Mini App is player-only; hub/profile/home pages send students back to the bot menu.
        Route::get('/', UseBotController::class)->name('home');
        Route::get('browse', UseBotController::class)->name('browse');
        Route::get('library', UseBotController::class)->name('library');
        Route::get('library/{hub}', UseBotController::class)
            ->whereIn('hub', ResourceHub::miniAppRouteSlugs())
            ->name('library.hub');
        Route::get('saved', UseBotController::class)->name('saved');
        Route::post('saved/{resource:slug}', [SavedController::class, 'toggle'])->name('saved.toggle');
        Route::get('opportunities/{opportunity:slug}', UseBotController::class)->name('opportunities.show');
        Route::post('opportunities/{opportunity:slug}/save', [OpportunityController::class, 'toggle'])->name('saved.opportunities.toggle');
        Route::post('opportunities/{opportunity:slug}/guidance', [OpportunityController::class, 'requestGuidance'])->name('opportunities.guidance');
        Route::get('courses/{course:slug}', UseBotController::class)->name('courses.show');
        Route::get('courses/{course:slug}/{hub}', UseBotController::class)
            ->whereIn('hub', ResourceHub::miniAppRouteSlugs())
            ->name('courses.hub');
        Route::get('resources/{resource:slug}', [App\Http\Controllers\Telegram\MiniApp\ResourceController::class, 'show'])->name('resources.show');
        Route::get('files/{resource:slug}/{index}', [ResourceFileController::class, 'show'])
            ->whereNumber('index')
            ->name('files.show');
        Route::get('play/exam/{resource:slug}', ExamPlayerController::class)->name('play.exam');
        Route::get('play/quiz/{resource:slug}', QuizPlayerController::class)->name('play.quiz');
        Route::post('play/quiz/{resource:slug}/result', QuizResultController::class)->name('play.quiz.result');
        Route::post('play/exam/{resource:slug}/result', ExamResultController::class)->name('play.exam.result');
        Route::get('play/reference-books/{resource:slug}', ReferenceBooksPlayerController::class)->name('play.reference-books');
        Route::get('play/daily-challenge', DailyChallengePlayerController::class)->name('play.daily-challenge');
        Route::post('play/daily-challenge/result', DailyChallengeResultController::class)->name('play.daily-challenge.result');
        Route::get('play/daily-challenge/leaderboard', DailyChallengeLeaderboardController::class)->name('play.daily-challenge.leaderboard');
        Route::post('play/questions/report', QuestionReportController::class)->name('play.question-report');
        Route::get('progress', ProgressController::class)->name('progress');
        Route::get('profile', UseBotController::class)->name('profile');
        Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::post('profile/academic', [ProfileController::class, 'updateAcademic'])->name('profile.academic');
        Route::post('profile/notifications', [ProfileController::class, 'toggleNotifications'])->name('profile.notifications');
        Route::post('profile/notifications/enable', [ProfileController::class, 'enableNotifications'])->name('profile.notifications.enable');
        Route::post('profile/locale', [ProfileController::class, 'updateLocale'])->name('profile.locale');
        Route::post('profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');
        Route::post('profile/referral-share', ReferralShareController::class)->name('profile.referral-share');
        Route::post('home-screen/prompted', [HomeScreenController::class, 'prompted'])->name('home-screen.prompted');
        Route::post('home-screen/added', [HomeScreenController::class, 'added'])->name('home-screen.added');
        Route::get('premium', [PremiumController::class, 'show'])->name('premium');
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

Route::post('/telegram/payment-bot/webhook', PaymentBotWebhookController::class)
    ->middleware(['throttle:60,1'])
    ->name('telegram.payment-bot.webhook');

Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::get('/telegram/webhook-status', [TelegramWebhookController::class, 'status'])
        ->name('telegram.webhook-status');

    Route::post('/telegram/webhook-status', [TelegramWebhookController::class, 'set'])
        ->name('telegram.webhook-set');

    Route::get('/telegram/payment-bot/webhook-status', [PaymentBotWebhookController::class, 'status'])
        ->name('telegram.payment-bot.webhook-status');

    Route::post('/telegram/payment-bot/webhook-status', [PaymentBotWebhookController::class, 'set'])
        ->name('telegram.payment-bot.webhook-set');
});
