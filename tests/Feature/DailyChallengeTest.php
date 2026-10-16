<?php

use App\Enums\OnboardingStep;
use App\Enums\UserRole;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Course;
use App\Models\DailyChallenge;
use App\Models\DailyChallengeAttempt;
use App\Models\LearningResource;
use App\Models\User;
use App\Models\UserStreak;
use App\Services\DailyChallengeGenerator;
use App\Services\DailyChallengeService;
use App\Services\SettingsService;
use App\Services\TelegramService;
use App\Support\DailyChallengeCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => '123456:TEST_TOKEN',
        'services.telegram.bot_username' => 'noviquni_bot',
        'app.url' => 'https://noviquni.test',
    ]);
});

/**
 * @return array{maths: Course, reading: Course, analytical: Course}
 */
function seedGatQuizBank(int $questionsPerSubject = 8): array
{
    $maths = Course::factory()->create(['name' => 'Maths', 'slug' => 'maths']);
    $reading = Course::factory()->create(['name' => 'Reading and writing', 'slug' => 'reading-and-writing']);
    $analytical = Course::factory()->create(['name' => 'Analytical reasoning', 'slug' => 'analytical-reasoning']);

    foreach ([$maths, $reading, $analytical] as $course) {
        $questions = [];
        for ($i = 0; $i < $questionsPerSubject; $i++) {
            $questions[] = [
                'question' => "{$course->slug} question {$i}?",
                'options' => ['A', 'B', 'C', 'D'],
                'answerIndex' => 0,
                'explanation' => 'A is correct.',
                'difficulty' => $i % 2 === 0 ? 'easy' : 'hard',
            ];
        }

        LearningResource::factory()->quiz()->create([
            'course_id' => $course->id,
            'is_published' => true,
            'content' => [
                'kind' => 'quiz',
                'payload' => [
                    'questions' => $questions,
                ],
            ],
        ]);
    }

    return [
        'maths' => $maths,
        'reading' => $reading,
        'analytical' => $analytical,
    ];
}

it('generates a daily challenge with subject quotas and skips recently used questions', function () {
    seedGatQuizBank(12);

    $generator = app(DailyChallengeGenerator::class);
    $today = DailyChallengeCalendar::today();

    $first = $generator->generate($today);
    expect($first['created'])->toBeTrue()
        ->and($first['challenge']->questions)->toHaveCount(10);

    $bySubject = collect($first['challenge']->questions)->countBy('course_slug');
    expect($bySubject->get('maths'))->toBe(4)
        ->and($bySubject->get('reading-and-writing'))->toBe(3)
        ->and($bySubject->get('analytical-reasoning'))->toBe(3);

    $again = $generator->generate($today);
    expect($again['created'])->toBeFalse()
        ->and($again['challenge']->id)->toBe($first['challenge']->id);

    $tomorrow = $today->copy()->addDay();
    $next = $generator->generate($tomorrow);
    $usedRefs = collect($first['challenge']->questions)->pluck('ref');
    $nextRefs = collect($next['challenge']->questions)->pluck('ref');

    expect($nextRefs->intersect($usedRefs))->toBeEmpty();
});

it('records an attempt with duration and updates streak and badges', function () {
    $dayOne = DailyChallenge::factory()->create([
        'challenge_date' => DailyChallengeCalendar::today()->subDays(2)->toDateString(),
    ]);
    $dayTwo = DailyChallenge::factory()->create([
        'challenge_date' => DailyChallengeCalendar::today()->subDay()->toDateString(),
    ]);
    $dayThree = DailyChallenge::factory()->create([
        'challenge_date' => DailyChallengeCalendar::todayDateString(),
    ]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $service = app(DailyChallengeService::class);

    $attempt = $service->recordAttempt($user, $dayOne, array_fill(0, 10, 0), 45_000);

    expect($attempt->score)->toBe(10)
        ->and($attempt->total)->toBe(10)
        ->and($attempt->duration_ms)->toBe(45_000);

    $service->recordAttempt($user, $dayTwo, array_fill(0, 10, 0), 40_000);
    $service->recordAttempt($user, $dayThree, array_fill(0, 10, 0), 30_000);

    $streak = $user->fresh()->streak;
    expect($streak)->not->toBeNull()
        ->and($streak->current_streak)->toBe(3)
        ->and($streak->longest_streak)->toBe(3)
        ->and($streak->unlockedBadges())->toContain(3);
});

it('resets streak when a day is missed', function () {
    $user = User::factory()->student()->create();
    $service = app(DailyChallengeService::class);

    UserStreak::factory()->create([
        'user_id' => $user->id,
        'current_streak' => 5,
        'longest_streak' => 5,
        'last_completed_on' => DailyChallengeCalendar::today()->subDays(2)->toDateString(),
        'badges' => [3],
    ]);

    $challenge = DailyChallenge::factory()->create([
        'challenge_date' => DailyChallengeCalendar::todayDateString(),
    ]);

    $service->recordAttempt($user, $challenge, array_fill(0, 10, 0), 12_000);

    expect($user->fresh()->streak->current_streak)->toBe(1);
});

it('puts daily challenge above continue studying until completed, then removes it', function () {
    $user = User::factory()->student()->create();
    $telegram = app(TelegramService::class);

    $incomplete = $telegram->mainKeyboard($user);
    expect(data_get($incomplete, 'keyboard.0.0.text'))->toBe('Daily challenge')
        ->and(data_get($incomplete, 'keyboard.1.0.text'))->toBe('Continue studying');

    $subjectIncomplete = $telegram->gatSubjectKeyboard($user);
    expect(data_get($subjectIncomplete, 'keyboard.0.0.text'))->toBe('Daily challenge')
        ->and(data_get($subjectIncomplete, 'keyboard.1.0.text'))->toBe('Continue studying');

    $challenge = DailyChallenge::factory()->create();
    DailyChallengeAttempt::factory()->create([
        'user_id' => $user->id,
        'daily_challenge_id' => $challenge->id,
    ]);

    $complete = $telegram->mainKeyboard($user->fresh());
    expect(data_get($complete, 'keyboard.0.0.text'))->toBe('Continue studying')
        ->and(collect(data_get($complete, 'keyboard'))->flatten(1)->pluck('text'))
        ->not->toContain('Daily challenge');

    $subjectComplete = $telegram->gatSubjectKeyboard($user->fresh());
    expect(data_get($subjectComplete, 'keyboard.0.0.text'))->toBe('Continue studying')
        ->and(collect(data_get($subjectComplete, 'keyboard'))->flatten(1)->pluck('text'))
        ->not->toContain('Daily challenge');
});

it('scores a daily challenge submission through the mini app', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'role' => UserRole::Student,
    ]);
    $challenge = DailyChallenge::factory()->create();

    $this->actingAs($user)
        ->postJson(route('tg.play.daily-challenge.result'), [
            'answers' => array_fill(0, 10, 0),
            'duration_ms' => 55_000,
        ])
        ->assertOk()
        ->assertJsonPath('score', 10)
        ->assertJsonPath('total', 10)
        ->assertJsonPath('duration_ms', 55_000)
        ->assertJsonPath('streak', 1)
        ->assertJsonPath('leaderboard_url', null);

    $this->actingAs($user)
        ->get(route('tg.play.daily-challenge.leaderboard'))
        ->assertNotFound();

    expect(DailyChallengeAttempt::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('shows the daily challenge leaderboard when enabled', function () {
    config(['daily_challenge.leaderboard_enabled' => true]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'role' => UserRole::Student,
        'name' => 'Leaderboard Student',
    ]);
    $challenge = DailyChallenge::factory()->create();

    DailyChallengeAttempt::factory()->create([
        'user_id' => $user->id,
        'daily_challenge_id' => $challenge->id,
        'score' => 10,
        'total' => 10,
        'duration_ms' => 40_000,
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.daily-challenge.leaderboard'))
        ->assertOk()
        ->assertSee('Leaderboard Student');
});

it('omits the leaderboard button after completing today when leaderboard is disabled', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $user = User::factory()->student()->create([
        'name' => 'Done Student',
        'telegram_id' => 555902,
        'telegram_locale' => 'en',
        'onboarding_step' => OnboardingStep::Complete,
    ]);
    $challenge = DailyChallenge::factory()->create();
    DailyChallengeAttempt::factory()->create([
        'user_id' => $user->id,
        'daily_challenge_id' => $challenge->id,
        'score' => 8,
        'total' => 10,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555902, 'daily challenge', 902))
        ->assertOk();

    $challengeReplies = Http::recorded(
        fn ($request): bool => str_contains((string) ($request->data()['text'] ?? ''), 'Nice work')
    );

    expect($challengeReplies)->not->toBeEmpty();

    [$request] = $challengeReplies[0];
    $data = $request->data();

    expect((string) ($data['text'] ?? ''))->toContain('Nice work')
        ->and((string) ($data['text'] ?? ''))->not->toContain('leaderboard')
        ->and(data_get($data, 'reply_markup.inline_keyboard'))->toBeNull()
        ->and(data_get($data, 'reply_markup.keyboard.0.0.text'))->toBe('Continue studying')
        ->and(collect(data_get($data, 'reply_markup.keyboard'))->flatten(1)->pluck('text'))
        ->not->toContain('Daily challenge');
});

it('dispatches reminders only to students who have not completed today', function () {
    Queue::fake();
    Http::fake();

    $challenge = DailyChallenge::factory()->create();

    $pending = User::factory()->student()->create([
        'telegram_id' => 111001,
        'notifications_enabled' => true,
        'is_active' => true,
        'onboarding_step' => OnboardingStep::Complete,
    ]);
    UserStreak::factory()->create([
        'user_id' => $pending->id,
        'current_streak' => 5,
        'last_completed_on' => DailyChallengeCalendar::today()->subDay()->toDateString(),
    ]);

    $done = User::factory()->student()->create([
        'telegram_id' => 111002,
        'notifications_enabled' => true,
        'is_active' => true,
    ]);
    DailyChallengeAttempt::factory()->create([
        'user_id' => $done->id,
        'daily_challenge_id' => $challenge->id,
    ]);

    $optedOut = User::factory()->student()->create([
        'telegram_id' => 111003,
        'notifications_enabled' => false,
        'is_active' => true,
    ]);

    Artisan::call('daily:challenge-remind');

    Queue::assertPushed(SendTelegramMessageJob::class, 1);
    Queue::assertPushed(SendTelegramMessageJob::class, function (SendTelegramMessageJob $job) use ($pending): bool {
        return (int) $job->chatId === (int) $pending->telegram_id
            && str_contains($job->text, '5');
    });
});

it('opens daily challenge from the reply keyboard', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $user = User::factory()->student()->create([
        'name' => 'Abebe Kebede',
        'telegram_id' => 555901,
        'onboarding_step' => OnboardingStep::Complete,
    ]);
    DailyChallenge::factory()->create();

    $this->postJson('/telegram/webhook', telegramMessagePayload(555901, 'daily challenge', 901))
        ->assertOk();

    Http::assertSent(function ($request) {
        $data = $request->data();
        $text = (string) ($data['text'] ?? '');

        return str_contains($text, 'Abebe')
            && str_contains($text, 'daily GAT challenge is ready')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.web_app.url') === route('tg.play.daily-challenge');
    });
});
