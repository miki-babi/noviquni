<?php

use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Enums\UserEventName;
use App\Enums\YearSlug;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\ResourceDownload;
use App\Models\Stream;
use App\Models\User;
use App\Models\UserEvent;
use App\Models\Year;
use App\Services\SettingsService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.bot_username' => 'noviquni_bot',
        'queue.default' => 'database',
        'app.url' => 'https://noviquni.test',
    ]);
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);
});

it('defaults new students to freshman and still shows the year picker on start', function () {
    $this->postJson('/telegram/webhook', telegramMessagePayload(700001, '/start'))->assertOk();

    $user = User::query()->where('telegram_id', '700001')->firstOrFail();

    expect($user->year()?->slug)->toBe(YearSlug::Freshman->value)
        ->and($user->onboarding_step)->toBe(OnboardingStep::Year)
        ->and(UserEvent::query()->where('user_id', $user->id)->where('event', UserEventName::Start)->exists())->toBeTrue();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage') && ! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $keyboard = data_get($request->data(), 'reply_markup.inline_keyboard');

        return is_array($keyboard)
            && collect($keyboard)->flatten(1)->contains(fn ($button) => ($button['callback_data'] ?? null) === 'ob:year:'.Year::query()->where('slug', YearSlug::Freshman->value)->value('id'));
    });
});

it('stores the first non-navigation start source and never overwrites it', function () {
    $this->postJson('/telegram/webhook', telegramMessagePayload(700002, '/start tikvah'))->assertOk();

    $user = User::query()->where('telegram_id', '700002')->firstOrFail();

    expect($user->start_source)->toBe('tikvah');

    $this->postJson('/telegram/webhook', telegramMessagePayload(700002, '/start other_ad', 2))->assertOk();

    expect($user->fresh()->start_source)->toBe('tikvah');
});

it('does not store navigation start payloads as start_source', function () {
    $this->postJson('/telegram/webhook', telegramMessagePayload(700003, '/start bait'))->assertOk();

    $user = User::query()->where('telegram_id', '700003')->firstOrFail();

    expect($user->start_source)->toBeNull();
});

it('logs resource_open with study details when a mini app resource is opened', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Anthropology']);
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);
    $user->courses()->attach($course);

    $resource = LearningResource::factory()->create([
        'title' => 'Chapter 3 — Kinship',
        'type' => ResourceType::Notes,
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'is_published' => true,
        'is_premium' => false,
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.notes', $resource))
        ->assertOk();

    expect(ResourceDownload::query()->where('user_id', $user->id)->where('learning_resource_id', $resource->id)->exists())->toBeTrue();

    $event = UserEvent::query()
        ->where('user_id', $user->id)
        ->where('event', UserEventName::ResourceOpen)
        ->latest('id')
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->details['course'])->toBe('Anthropology')
        ->and($event->details['chapter'])->toBe('Chapter 3 — Kinship')
        ->and($event->details['type'])->toBe('note')
        ->and($event->details['resource_type'])->toBe(ResourceType::Notes->value);
});

it('logs quiz_done when a quiz result is posted', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Anthropology']);
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);
    $user->courses()->attach($course);

    $resource = LearningResource::factory()->create([
        'title' => 'Kinship Quiz',
        'type' => ResourceType::Quiz,
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'is_published' => true,
        'is_premium' => false,
    ]);

    $this->actingAs($user)
        ->postJson(route('tg.play.quiz.result', $resource), [
            'score' => 3,
            'total' => 5,
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $event = UserEvent::query()
        ->where('user_id', $user->id)
        ->where('event', UserEventName::QuizDone)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->details['score'])->toBe(3)
        ->and($event->details['total'])->toBe(5)
        ->and($event->details['course'])->toBe('Anthropology');
});

it('logs referral_joined when a referral start code attributes a new student', function () {
    $referrer = User::factory()->student()->create([
        'referral_code' => 'REFCODE1',
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(700004, '/start REFCODE1'))->assertOk();

    $referred = User::query()->where('telegram_id', '700004')->firstOrFail();

    expect($referred->referred_by_user_id)->toBe($referrer->id)
        ->and($referred->start_source)->toBe('REFCODE1')
        ->and(UserEvent::query()->where('user_id', $referred->id)->where('event', UserEventName::ReferralJoined)->exists())->toBeTrue();
});

it('logs blocked when telegram refuses a message because the user blocked the bot', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '700005',
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $factory = new Factory;
    $factory->fake([
        '*' => Http::response([
            'ok' => false,
            'error_code' => 403,
            'description' => 'Forbidden: bot was blocked by the user',
        ], 403),
    ]);
    Http::swap($factory);

    app(TelegramService::class)->sendMessage($user->telegram_id, 'Hello');

    expect(UserEvent::query()
        ->where('user_id', $user->id)
        ->where('event', UserEventName::Blocked)
        ->exists())->toBeTrue();
});

it('logs onboarding_done with stream details', function () {
    $freshman = Year::query()->where('slug', YearSlug::Freshman->value)->firstOrFail();
    $stream = Stream::factory()->create(['name' => 'Natural']);

    $this->postJson('/telegram/webhook', telegramMessagePayload(700006, '/start'))->assertOk();
    $this->postJson('/telegram/webhook', telegramCallbackPayload(700006, "ob:year:{$freshman->id}"))->assertOk();
    $this->postJson('/telegram/webhook', telegramCallbackPayload(700006, "ob:stream:{$stream->id}", 2, 3))->assertOk();

    $user = User::query()->where('telegram_id', '700006')->firstOrFail();

    $event = UserEvent::query()
        ->where('user_id', $user->id)
        ->where('event', UserEventName::OnboardingDone)
        ->first();

    expect($user->onboarding_step)->toBe(OnboardingStep::Complete)
        ->and($event)->not->toBeNull()
        ->and($event->details['stream'])->toBe('Natural');
});
