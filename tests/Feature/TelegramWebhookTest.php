<?php

use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Enums\YearSlug;
use App\Jobs\ProcessTelegramUpdateJob;
use App\Models\Bookmark;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Opportunity;
use App\Models\Stream;
use App\Models\University;
use App\Models\User;
use App\Models\Year;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => 'test-token',
        'queue.default' => 'database',
        'app.url' => 'https://noviquni.test',
    ]);
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);
});

it('accepts a start update and creates a completed GAT student without onboarding', function () {
    Queue::fake();

    $maths = Course::factory()->create(['name' => 'Maths', 'slug' => 'maths', 'is_active' => true]);
    $reading = Course::factory()->create(['name' => 'Reading and writing', 'slug' => 'reading-and-writing', 'is_active' => true]);
    $analytical = Course::factory()->create(['name' => 'Analytical reasoning', 'slug' => 'analytical-reasoning', 'is_active' => true]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555001, '/start'))
        ->assertOk();

    $user = User::query()->where('telegram_id', '555001')->firstOrFail();

    expect($user->onboarding_step)->toBe(OnboardingStep::Complete)
        ->and($user->is_gat)->toBeTrue()
        ->and($user->stream_id)->toBeNull()
        ->and($user->courses()->pluck('courses.id')->sort()->values()->all())
        ->toBe(collect([$maths->id, $reading->id, $analytical->id])->sort()->values()->all());

    Queue::assertNotPushed(ProcessTelegramUpdateJob::class);

    Http::assertNotSent(function ($request) {
        $text = (string) data_get($request->data(), 'text');

        return str_contains($text, 'What year are you in')
            || str_contains($text, 'Tap your stream')
            || str_contains($text, 'What is your department');
    });

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Your study menu is ready')
            && data_get($data, 'reply_markup.keyboard.0.0.text') === 'Daily challenge'
            && data_get($data, 'reply_markup.keyboard.5.1.text') === 'Refer';
    });

    Http::assertNotSent(function ($request) {
        $text = (string) data_get($request->data(), 'text');

        return str_contains($text, 'Welcome back')
            || str_contains($text, 'Start now');
    });
});

it('marks new students as GAT on /start', function () {
    $this->postJson('/telegram/webhook', telegramMessagePayload(555012, '/start', 12))
        ->assertOk();

    $user = User::query()->where('telegram_id', '555012')->firstOrFail();

    expect($user->is_gat)->toBeTrue()
        ->and($user->onboarding_step)->toBe(OnboardingStep::Complete);
});

it('auto-enrolls GAT courses for existing students with an empty course list', function () {
    $maths = Course::factory()->create(['name' => 'Maths', 'slug' => 'maths', 'is_active' => true]);
    Course::factory()->create(['name' => 'Reading and writing', 'slug' => 'reading-writing', 'is_active' => true]);
    Course::factory()->create(['name' => 'Analytical reasoning', 'slug' => 'analytical-reasoning', 'is_active' => true]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555013',
        'onboarding_step' => OnboardingStep::Complete,
        'is_gat' => true,
        'is_active' => true,
    ]);

    expect($user->courses()->count())->toBe(0);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555013, '/start', 13))
        ->assertOk();

    expect($user->fresh()->courses()->pluck('courses.slug')->sort()->values()->all())
        ->toBe(['analytical-reasoning', 'maths', 'reading-writing']);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Welcome back')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.web_app.url') === route('tg.browse');
    });
});

it('treats legacy university and course onboarding callbacks as stale', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555013',
        'onboarding_step' => OnboardingStep::Stream,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555013, 'ob:uni:skip', 20, 3))->assertOk();
    $this->postJson('/telegram/webhook', telegramCallbackPayload(555013, 'ob:sem:skip', 20, 4))->assertOk();
    $this->postJson('/telegram/webhook', telegramCallbackPayload(555013, 'ob:course:confirm', 20, 5))->assertOk();

    $user->refresh();

    expect($user->onboarding_step)->toBe(OnboardingStep::Stream)
        ->and($user->stream_id)->toBeNull();
});

it('shows setup CTA on start when enrolled user has no courses', function () {
    User::factory()->student()->create([
        'telegram_id' => '555020',
        'name' => 'Mikiyas Kebede',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555020, '/start', 400))->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'welcome to Noviq Uni')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.web_app.url') === route('tg.profile.edit')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.text') === '🎯 Set up in Profile';
    });
});

it('shows continue studying CTA on start when user has courses', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Physics']);

    $user = User::factory()->student()->create([
        'telegram_id' => '555021',
        'name' => 'Mikiyas',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555021, '/start', 401))->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Welcome back, Abebe')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.web_app.url') === route('tg.browse');
    });
});

it('shows enrolled courses in a reply keyboard when Courses is tapped', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Physics',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555004',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555004, '📚 Courses'))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Your courses — tap one to study')
            && data_get($data, 'reply_markup.keyboard.0.0.text') === '📗 Physics'
            && data_get($data, 'reply_markup.keyboard.1.0.text') === '← Back'
            && data_get($data, 'reply_markup.inline_keyboard') === null;
    });
});

it('restores the main reply keyboard when Back is tapped from courses', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555024',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);
    $user->syncYear(Year::query()->where('slug', YearSlug::Freshman->value)->firstOrFail());

    $this->postJson('/telegram/webhook', telegramMessagePayload(555024, '← Back'))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return data_get($data, 'reply_markup.keyboard.0.0.text') === 'Daily challenge'
            && data_get($data, 'reply_markup.keyboard.1.0.text') === 'Maths'
            && data_get($data, 'reply_markup.keyboard.1.1.text') === 'Reading/writing'
            && data_get($data, 'reply_markup.keyboard.2.0.text') === 'Analytical reasoning'
            && data_get($data, 'reply_markup.keyboard.3.0.text') === '🔖 Quick saved'
            && data_get($data, 'reply_markup.keyboard.4.0.text') === 'Unlock premium'
            && data_get($data, 'reply_markup.keyboard.5.0.text') === 'Contact us'
            && data_get($data, 'reply_markup.keyboard.5.1.text') === 'Refer';
    });
});

it('shows available resource types in a reply keyboard when Resources is tapped', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Physics']);
    $user = User::factory()->student()->create([
        'telegram_id' => '555025',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    LearningResource::factory()->published()->referenceBooks()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Motion notes',
    ]);

    LearningResource::factory()->published()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Motion quiz',
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555025, '📚 Freshman resources'))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.keyboard', []))
            ->flatten(1)
            ->keyBy('text');

        $references = $buttons->get('📚 Reference books');
        $quiz = $buttons->get('📚 Quiz');
        $back = $buttons->get('← Back');

        return str_contains((string) ($data['text'] ?? ''), 'Resources across your courses')
            && $references !== null
            && $quiz !== null
            && $back !== null
            && ($references['style'] ?? null) === 'danger'
            && ($quiz['style'] ?? null) === 'success'
            && ! array_key_exists('style', $back);
    });
});

it('shows courses with the selected resource type in a reply keyboard', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $user = User::factory()->student()->create([
        'telegram_id' => '555026',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    LearningResource::factory()->published()->referenceBooks()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555026, '📚 Reference books'))
        ->assertOk();

    Http::assertSent(function ($request) use ($course) {
        $data = $request->data();

        return str_contains($request->url(), '/sendMessage')
            && str_contains((string) ($data['text'] ?? ''), 'Choose a course for Reference books')
            && data_get($data, 'reply_markup.keyboard.0.0.text') === '📗 '.$course->name
            && data_get($data, 'reply_markup.keyboard.1.0.text') === '← Back';
    });
});

it('sends the file directly when a course has one file-backed resource of the selected type', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Physics']);
    $user = User::factory()->student()->create([
        'telegram_id' => '555028',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);
    $resource = LearningResource::factory()->published()->referenceBooks()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Motion notes',
        'files' => null,
        'telegram_files' => [
            ['file_id' => 'BQACAgQAAxkBAAI-direct-notes', 'file_name' => 'notes.docx'],
        ],
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555028, '📚 Reference books', 600))->assertOk();
    $this->postJson('/telegram/webhook', telegramMessagePayload(555028, '📗 Physics', 601))->assertOk();

    Http::assertSent(function ($request) use ($resource) {
        if (! str_contains($request->url(), '/sendDocument')) {
            return false;
        }

        $data = $request->data();

        return ($data['document'] ?? null) === 'BQACAgQAAxkBAAI-direct-notes'
            && ($data['caption'] ?? null) === $resource->title;
    });

    Http::assertNotSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Open Reference books for Physics in the study app')
            || filled(data_get($data, 'reply_markup.inline_keyboard.0.0.web_app.url'));
    });
});

it('lists resources inline when a course has multiple resources of the selected type', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Chemistry']);
    $user = User::factory()->student()->create([
        'telegram_id' => '555029',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $first = LearningResource::factory()->published()->referenceBooks()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Week 1 notes',
        'files' => null,
        'telegram_files' => [
            ['file_id' => 'BQACAgQAAxkBAAI-week1', 'file_name' => 'week1.docx'],
        ],
    ]);
    $second = LearningResource::factory()->published()->referenceBooks()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Week 2 notes',
        'files' => null,
        'telegram_files' => [
            ['file_id' => 'BQACAgQAAxkBAAI-week2', 'file_name' => 'week2.docx'],
        ],
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555029, '📚 Reference books', 602))->assertOk();
    $this->postJson('/telegram/webhook', telegramMessagePayload(555029, '📗 Chemistry', 603))->assertOk();

    Http::assertSent(function ($request) use ($course, $first, $second) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), $course->name)
            && data_get($data, 'reply_markup.inline_keyboard.0.0.callback_data') === 'open_resource:'.$first->id
            && data_get($data, 'reply_markup.inline_keyboard.1.0.callback_data') === 'open_resource:'.$second->id
            && data_get($data, 'reply_markup.inline_keyboard.0.0.web_app') === null;
    });

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sendDocument'));
});

it('shows course setup for the legacy Browse label when no courses are enrolled', function () {
    User::factory()->student()->create([
        'telegram_id' => '555014',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555014, '📖 Browse', 500))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return data_get($data, 'reply_markup.inline_keyboard.0.0.web_app.url') === route('tg.profile.edit');
    });
});

it('shows coming soon browse state without counts when enrolled but empty', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Mathematics',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555022',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555022, '📖 Browse', 402))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Your courses — tap one to study')
            && data_get($data, 'reply_markup.keyboard.0.0.text') === '📗 Mathematics'
            && data_get($data, 'reply_markup.keyboard.1.0.text') === '← Back';
    });
});

it('shows course resource types after course tap', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Physics',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555023',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    LearningResource::factory()->published()->referenceBooks()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Week-1 notes',
    ]);
    LearningResource::factory()->published()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Physics Quiz',
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555023, "course:{$course->id}", 30, 403))
        ->assertOk();

    Http::assertSent(function ($request) use ($course) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        $refBooksButton = $buttons->first(fn (array $button) => ($button['callback_data'] ?? '') === "rtype:reference_books:{$course->id}");
        $quizButton = $buttons->first(fn (array $button) => ($button['callback_data'] ?? '') === "rtype:quiz:{$course->id}");

        return str_contains((string) ($data['text'] ?? ''), 'Physics')
            && str_contains((string) ($data['text'] ?? ''), 'choose a type')
            && is_array($refBooksButton)
            && ($refBooksButton['text'] ?? null) === 'Reference books'
            && is_array($quizButton)
            && ($quizButton['text'] ?? null) === 'Quiz'
            && $buttons->every(fn (array $button) => ! str_starts_with((string) ($button['callback_data'] ?? ''), 'rtype_soon:'))
            && $buttons->every(fn (array $button) => ($button['web_app']['url'] ?? null) !== route('tg.courses.show', $course));
    });
});

it('shows course coming soon when no published resource types exist', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Physics',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555035',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555035, "course:{$course->id}", 31, 4031))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Physics')
            && str_contains((string) ($data['text'] ?? ''), 'coming soon')
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === 'notify:on')
            && $buttons->every(fn (array $button) => ! str_starts_with((string) ($button['callback_data'] ?? ''), 'rtype:'));
    });
});

it('shows continue resume card when a prior download exists', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Mathematics',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555024',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $resource = LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Chapter notes',
    ]);
    $user->downloads()->create(['learning_resource_id' => $resource->id]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555024, '📚 Continue', 404))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Pick up where you left off')
            && str_contains((string) ($data['text'] ?? ''), 'Mathematics')
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === 'continue:resume')
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === 'continue:switch');
    });
});

it('shows profile submenu with notifications refer and settings', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555025',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555025, '👤 Profile', 405))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Open Profile to set your university')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.web_app.url') === route('tg.profile.edit')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.text') === '👤 Edit study profile';
    });
});

it('switches language and refreshes keyboard labels', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555026',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
        'telegram_locale' => 'en',
    ]);
    $user->syncYear(Year::query()->where('slug', YearSlug::Freshman->value)->firstOrFail());

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555026, 'settings:lang:am', 30, 406))
        ->assertOk();

    $user = User::query()->where('telegram_id', '555026')->firstOrFail();
    expect($user->telegram_locale)->toBe('am');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return data_get($data, 'reply_markup.keyboard.0.0.text') === 'ዕለታዊ ፈተና'
            && data_get($data, 'reply_markup.keyboard.1.0.text') === 'ሂሳብ'
            && data_get($data, 'reply_markup.keyboard.1.1.text') === 'ንባብ/ጽሁፍ'
            && data_get($data, 'reply_markup.keyboard.2.0.text') === 'አመክንዮአዊ ትንተና'
            && data_get($data, 'reply_markup.keyboard.3.0.text') === '🔖 በፍጥነት የተቀመጡ'
            && data_get($data, 'reply_markup.keyboard.4.0.text') === 'ፕሪሚየም ክፈት'
            && data_get($data, 'reply_markup.keyboard.5.0.text') === 'ያግኙን'
            && data_get($data, 'reply_markup.keyboard.5.1.text') === 'ይጋብዙ';
    });
});

it('lists scholarship opportunities when the keyboard button is tapped', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555226',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);
    $user->syncYear(Year::query()->where('slug', YearSlug::Freshman->value)->firstOrFail());

    Opportunity::factory()->published()->scholarship()->create([
        'title' => 'Webhook Scholarship Post',
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555226, '🎓 Scholarship opportunities', 426))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Scholarships')
            && $buttons->contains(fn (array $button) => ($button['text'] ?? '') === 'Webhook Scholarship Post');
    });
});

it('still routes the legacy My Courses label to the course reply keyboard', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Chemistry']);

    $user = User::factory()->student()->create([
        'telegram_id' => '555027',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555027, '📚 My Courses', 407))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return data_get($data, 'reply_markup.keyboard.0.0.text') === '📗 Chemistry'
            && data_get($data, 'reply_markup.keyboard.1.0.text') === '← Back';
    });
});

it('completes mid-funnel users when they send free text instead of asking for year', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555005',
        'onboarding_step' => OnboardingStep::Year,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555005, 'Social', 2))->assertOk();

    $user->refresh();

    expect($user->onboarding_step)->toBe(OnboardingStep::Complete)
        ->and($user->stream_id)->toBeNull();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return data_get($request->data(), 'reply_markup.keyboard.0.0.text') === 'Daily challenge';
    });
});

it('completes mid-funnel users with a stream on /start without re-asking stream', function () {
    $stream = Stream::factory()->create(['name' => 'Natural']);

    $user = User::factory()->student()->create([
        'telegram_id' => '555006',
        'onboarding_step' => OnboardingStep::University,
        'stream_id' => $stream->id,
        'name' => 'Mikiyas',
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555006, '/start', 100))->assertOk();

    $user->refresh();

    expect($user->onboarding_step)->toBe(OnboardingStep::Complete);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && str_contains((string) data_get($request->data(), 'text'), 'welcome to Noviq Uni');
    });
});

it('does not revive legacy university callbacks for mid-funnel users', function () {
    $stream = Stream::factory()->create(['name' => 'Natural']);

    $user = User::factory()->student()->create([
        'telegram_id' => '555007',
        'onboarding_step' => OnboardingStep::Courses,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555007, 'ob:uni:skip', 20, 101))
        ->assertOk();

    $user->refresh();

    expect($user->onboarding_step)->toBe(OnboardingStep::Courses);

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && str_contains((string) data_get($request->data(), 'text'), 'semester');
    });
});

it('does not resend onboarding complete message for stale confirm callbacks', function () {
    $stream = Stream::factory()->create(['name' => 'Natural']);
    Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Math']);

    $user = User::factory()->student()->create([
        'telegram_id' => '555008',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555008, 'ob:course:confirm', 20, 102))
        ->assertOk();

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && str_contains((string) data_get($request->data(), 'text'), 'Onboarding complete');
    });
});

it('ignores duplicate update ids', function () {
    University::factory()->create(['name' => 'AAU']);

    $payload = telegramMessagePayload(555009, '/start', 200);

    $this->postJson('/telegram/webhook', $payload)->assertOk();
    $this->postJson('/telegram/webhook', $payload)->assertOk();

    $user = User::query()->where('telegram_id', '555009')->firstOrFail();

    expect($user->onboarding_step)->toBe(OnboardingStep::Complete)
        ->and($user->is_gat)->toBeTrue();

    $startMenus = collect(Http::recorded())
        ->filter(function (array $record): bool {
            $url = $record[0]->url();
            $text = (string) data_get($record[0]->data(), 'text');

            return str_contains($url, '/sendMessage')
                && str_contains($text, 'Your study menu is ready');
        })
        ->count();

    expect($startMenus)->toBe(1);
});

it('uses editMessageText for back to courses navigation', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Biology']);

    $user = User::factory()->student()->create([
        'telegram_id' => '555010',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555010, 'back:courses', 30, 103))
        ->assertOk();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/editMessageText')
            && str_contains((string) data_get($request->data(), 'text'), 'Your courses — tap one to study');
    });

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && str_contains((string) data_get($request->data(), 'text'), 'Your courses — tap one to study');
    });
});

it('completes mid-funnel users on free text without onboarding reprompts', function () {
    User::factory()->student()->create([
        'telegram_id' => '555011',
        'onboarding_step' => OnboardingStep::Stream,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555011, 'hello', 301))->assertOk();

    $user = User::query()->where('telegram_id', '555011')->firstOrFail();

    expect($user->onboarding_step)->toBe(OnboardingStep::Complete);

    Http::assertNotSent(function ($request) {
        $text = (string) data_get($request->data(), 'text');

        return str_contains($text, 'What year are you in')
            || str_contains($text, 'Tap your stream');
    });
});

it('opens a bait course path from /start bait', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Chemistry',
        'slug' => 'chemistry',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555030',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    LearningResource::factory()->published()->referenceBooks()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Bait notes',
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555030, '/start bait', 500))
        ->assertOk();

    Http::assertSent(function ($request) use ($course) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Chemistry')
            && str_contains((string) ($data['text'] ?? ''), 'choose a type')
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === "rtype:reference_books:{$course->id}");
    });
});

it('opens a resource from /start resource deep link', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555031',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $resource = LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Deep link notes',
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555031, '/start resource_'.$resource->id, 501))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return str_contains((string) data_get($request->data(), 'text'), 'Deep link notes');
    });
});

it('opens a course from /start course deep link', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'English',
        'slug' => 'english',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555032',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);

    LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'English bait',
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555032, '/start course_english', 502))
        ->assertOk();

    expect($user->fresh()->courses()->where('courses.id', $course->id)->exists())->toBeTrue();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) data_get($request->data(), 'text');

        return str_contains($text, 'English') && str_contains($text, 'choose a type');
    });
});

it('opens a mini app button when continue studying is tapped', function () {
    User::factory()->student()->create([
        'telegram_id' => '555301',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555301, 'continue studying', 601))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'No courses yet')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.web_app.url') === route('tg.profile.edit');
    });
});

it('opens the maths subject reply keyboard when maths is tapped and the course exists', function () {
    $course = Course::factory()->create(['name' => 'Maths', 'slug' => 'maths']);

    $user = User::factory()->student()->create([
        'telegram_id' => '555302',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    expect($user->courses()->where('courses.id', $course->id)->exists())->toBeFalse();

    $this->postJson('/telegram/webhook', telegramMessagePayload(555302, 'maths', 602))
        ->assertOk();

    expect($user->fresh()->courses()->where('courses.id', $course->id)->exists())->toBeTrue();

    Http::assertSent(function ($request) use ($course) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), $course->name)
            && data_get($data, 'reply_markup.keyboard.0.0.text') === 'Practice exams'
            && data_get($data, 'reply_markup.keyboard.0.1.text') === 'Quiz'
            && data_get($data, 'reply_markup.keyboard.1.0.text') === 'Reference books'
            && data_get($data, 'reply_markup.keyboard.1.1.text') === 'Past exams'
            && data_get($data, 'reply_markup.keyboard.2.0.text') === '← Back';
    });
});

it('tells the student when reading/writing is tapped without a matching course', function () {
    User::factory()->student()->create([
        'telegram_id' => '555303',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555303, 'reading/writing', 603))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'That course is not set up yet')
            && data_get($data, 'reply_markup.keyboard.2.0.text') === 'Maths';
    });
});

it('opens reading/writing when the course uses Reading and writing naming', function () {
    $course = Course::factory()->create([
        'name' => 'Reading and writing',
        'slug' => 'reading-and-writing',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '5553031',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(5553031, 'reading/writing', 6031))
        ->assertOk();

    expect($user->fresh()->courses()->where('courses.id', $course->id)->exists())->toBeTrue();

    Http::assertSent(function ($request) use ($course) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), $course->name)
            && data_get($data, 'reply_markup.keyboard.0.0.text') === 'Practice exams';
    });
});

it('opens Analytical reasoning when the matching course exists', function () {
    $course = Course::factory()->create([
        'name' => 'Analytical reasoning',
        'slug' => 'analytical-reasoning',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '5553032',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(5553032, 'Analytical reasoning', 6032))
        ->assertOk();

    expect($user->fresh()->courses()->where('courses.id', $course->id)->exists())->toBeTrue();

    Http::assertSent(function ($request) use ($course) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), $course->name)
            && data_get($data, 'reply_markup.keyboard.0.0.text') === 'Practice exams';
    });
});

it('lists quiz resources for the selected gat subject course', function () {
    $course = Course::factory()->create(['name' => 'Maths', 'slug' => 'maths']);
    $user = User::factory()->student()->create([
        'telegram_id' => '555304',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    LearningResource::factory()->published()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $course->stream_id,
        'title' => 'Algebra quiz',
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555304, 'maths', 604))
        ->assertOk();
    $this->postJson('/telegram/webhook', telegramMessagePayload(555304, 'quiz', 605))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage') && ! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $text = (string) ($data['text'] ?? '');

        return str_contains($text, 'Algebra quiz')
            || data_get($data, 'reply_markup.inline_keyboard.0.0.text') === 'Algebra quiz'
            || str_contains((string) data_get($data, 'reply_markup.inline_keyboard.0.0.text'), 'Algebra quiz');
    });
});

it('paginates quiz resources when a gat subject has more than one page', function () {
    $course = Course::factory()->create(['name' => 'Maths', 'slug' => 'maths']);
    $user = User::factory()->student()->create([
        'telegram_id' => '555306',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    foreach (range(1, 10) as $index) {
        LearningResource::factory()->published()->quiz()->create([
            'course_id' => $course->id,
            'stream_id' => $course->stream_id,
            'title' => sprintf('Quiz %02d', $index),
        ]);
    }

    $this->postJson('/telegram/webhook', telegramMessagePayload(555306, 'Maths', 706))
        ->assertOk();
    $this->postJson('/telegram/webhook', telegramMessagePayload(555306, 'Quiz', 707))
        ->assertOk();

    $nextCallback = "resources:page:type:{$course->id}:quiz:1";

    Http::assertSent(function ($request) use ($course, $nextCallback) {
        if (! str_contains($request->url(), '/sendMessage') && ! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);
        $resourceButtons = $buttons->filter(
            fn (array $button): bool => filled($button['web_app']['url'] ?? null)
                || str_starts_with((string) ($button['callback_data'] ?? ''), 'open:'),
        );

        return str_contains((string) ($data['text'] ?? ''), 'Maths')
            && str_contains((string) ($data['text'] ?? ''), '(1/2)')
            && $resourceButtons->count() === 8
            && $buttons->count() <= 100
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === $nextCallback
                && ($button['text'] ?? '') === 'Next ›')
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === "course:{$course->id}");
    });

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555306, $nextCallback, 80, 708))
        ->assertOk();

    Http::assertSent(function ($request) use ($course) {
        if (! str_contains($request->url(), '/sendMessage') && ! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);
        $resourceButtons = $buttons->filter(
            fn (array $button): bool => filled($button['web_app']['url'] ?? null)
                || str_starts_with((string) ($button['callback_data'] ?? ''), 'open:'),
        );

        return str_contains((string) ($data['text'] ?? ''), '(2/2)')
            && $resourceButtons->count() === 2
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === "resources:page:type:{$course->id}:quiz:0"
                && ($button['text'] ?? '') === '‹ Back')
            && $buttons->count() <= 100;
    });
});

it('opens the support telegram handle when contact us is tapped', function () {
    User::factory()->student()->create([
        'telegram_id' => '555304',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555304, 'Contact us', 604))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), '@mikiyas_sh')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.url') === 'https://t.me/mikiyas_sh';
    });
});

it('opens a forwardable start message from the invite friends reply keyboard label', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555033',
        'name' => 'Mikiyas',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555033, '👥 Invite friends', 503))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) data_get($request->data(), 'text');

        return str_contains($text, 'Refer friends')
            && str_contains($text, 'Forward the message below')
            && ! str_contains($text, 'Progress:');
    });

    Http::assertSent(function ($request) use ($user) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $text = (string) ($data['text'] ?? '');

        return str_contains($text, 'Join me on Noviq Uni for GAT prep')
            && str_contains($text, 'Daily challenge')
            && str_contains($text, 'Start now</a>')
            && str_contains($text, 'start='.$user->referral_code)
            && ! str_contains($text, 'Welcome back')
            && ! array_key_exists('reply_markup', $data);
    });
});

it('opens refer invite from the Refer reply keyboard label', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555305',
        'name' => 'Mikiyas',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555305, 'Refer', 605))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) data_get($request->data(), 'text');

        return str_contains($text, 'Refer friends')
            && str_contains($text, 'Forward the message below')
            && ! str_contains($text, 'Points:');
    });

    Http::assertSent(function ($request) use ($user) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $text = (string) ($data['text'] ?? '');

        return str_contains($text, 'Join me on Noviq Uni for GAT prep')
            && str_contains($text, 'Start now</a>')
            && str_contains($text, 'start='.$user->referral_code)
            && ! str_contains($text, 'Welcome back')
            && ! array_key_exists('reply_markup', $data);
    });
});

it('still opens refer flow from the legacy refer and earn keyboard label', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555133',
        'name' => 'Mikiyas',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555133, '👥 Refer and earn', 504))
        ->assertOk();

    Http::assertSent(function ($request) use ($user) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $text = (string) ($data['text'] ?? '');

        return str_contains($text, 'Join me on Noviq Uni for GAT prep')
            && str_contains($text, 'Start now</a>')
            && str_contains($text, 'start='.$user->referral_code)
            && ! str_contains($text, 'Welcome back')
            && ! array_key_exists('reply_markup', $data);
    });
});
it('still opens continue from the legacy continue label', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Biology']);

    $user = User::factory()->student()->create([
        'telegram_id' => '555034',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $resource = LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Legacy continue notes',
    ]);
    $user->downloads()->create([
        'learning_resource_id' => $resource->id,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555034, '📚 Continue', 504))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return str_contains((string) data_get($request->data(), 'text'), 'Pick up where you left off');
    });
});

it('lists quick saved as chat buttons newest first without opening the Mini App', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Physics',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555040',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $older = LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Older notes',
        'type' => ResourceType::ReferenceBooks,
    ]);
    $newer = LearningResource::factory()->published()->worksheet()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Newer worksheet',
        'type' => ResourceType::ReferenceBooks,
    ]);

    Bookmark::query()->create([
        'user_id' => $user->id,
        'bookmarkable_type' => $older->getMorphClass(),
        'bookmarkable_id' => $older->id,
    ]);
    Bookmark::query()->create([
        'user_id' => $user->id,
        'bookmarkable_type' => $newer->getMorphClass(),
        'bookmarkable_id' => $newer->id,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555040, '🔖 Quick saved', 540))
        ->assertOk();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sendMessage')
        && data_get($request->data(), 'reply_markup.keyboard') !== null);

    Http::assertSent(function ($request) use ($older, $newer) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $rows = collect(data_get($data, 'reply_markup.inline_keyboard', []));
        $buttons = $rows->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Quick saved (1/1)')
            && data_get($rows, '0.0.web_app.url') === route('tg.play.reference-books', $newer)
            && data_get($rows, '0.0.style') === 'success'
            && data_get($rows, '1.0.web_app.url') === route('tg.play.reference-books', $older)
            && data_get($rows, '1.0.style') === 'success'
            && $buttons->contains(fn (array $button) => ($button['text'] ?? '') === 'Newer worksheet')
            && $buttons->contains(fn (array $button) => ($button['text'] ?? '') === 'Older notes')
            && $buttons->every(fn (array $button) => ! isset($button['callback_data'])
                || str_starts_with((string) $button['callback_data'], 'saved:page:'));
    });
});

it('paginates quick saved with next and back callbacks', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Chemistry',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555041',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    foreach (range(1, 6) as $index) {
        $resource = LearningResource::factory()->published()->notes()->create([
            'course_id' => $course->id,
            'stream_id' => $stream->id,
            'title' => "Saved notes {$index}",
        ]);

        Bookmark::query()->create([
            'user_id' => $user->id,
            'bookmarkable_type' => $resource->getMorphClass(),
            'bookmarkable_id' => $resource->id,
        ]);
    }

    $this->postJson('/telegram/webhook', telegramMessagePayload(555041, '🔖 Quick saved', 541))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $rows = collect(data_get($data, 'reply_markup.inline_keyboard', []));
        $buttons = $rows->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Quick saved (1/2)')
            && $rows->count() === 6
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === 'saved:page:1'
                && ($button['text'] ?? '') === 'Next ›')
            && $buttons->every(fn (array $button) => ($button['callback_data'] ?? '') !== 'saved:page:0');
    });

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555041, 'saved:page:1', 99, 542))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Quick saved (2/2)')
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === 'saved:page:0'
                && ($button['text'] ?? '') === '‹ Back')
            && $buttons->every(fn (array $button) => ($button['callback_data'] ?? '') !== 'saved:page:1');
    });
});

it('shows member privileges and premium description when Unlock premium is tapped', function () {
    app(SettingsService::class)->set(SettingsService::PAYMENT_BOT_USERNAME, 'noviquni_pay_bot');

    $user = User::factory()->student()->create([
        'telegram_id' => '555050',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555050, 'Unlock premium'))
        ->assertOk();

    Http::assertSent(function ($request) use ($user) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $text = (string) ($data['text'] ?? '');
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains($text, 'Included with Premium')
            && str_contains($text, '2,100+ practice questions')
            && str_contains($text, '279 quizzes &amp; practice exams')
            && str_contains($text, 'Price: ')
            && str_contains($text, ' ETB')
            && $buttons->contains(fn (array $button) => ($button['text'] ?? '') === 'Become Premium'
                && ($button['url'] ?? '') === 'https://t.me/noviquni_pay_bot?start=u'.$user->id)
            && $buttons->contains(fn (array $button) => ($button['text'] ?? '') === 'Premium description'
                && str_contains((string) data_get($button, 'web_app.url'), '/tg/premium'));
    });
});

it('shows member privileges and premium description for premium_pay callbacks', function () {
    app(SettingsService::class)->set(SettingsService::PAYMENT_BOT_USERNAME, 'noviquni_pay_bot');

    $user = User::factory()->student()->create([
        'telegram_id' => '555051',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555051, 'premium_pay'))
        ->assertOk();

    Http::assertSent(function ($request) use ($user) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $text = (string) ($data['text'] ?? '');
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains($text, 'Included with Premium')
            && str_contains($text, '2,100+ practice questions')
            && str_contains($text, 'New questions every weekday')
            && $buttons->contains(fn (array $button) => ($button['text'] ?? '') === 'Become Premium'
                && ($button['url'] ?? '') === 'https://t.me/noviquni_pay_bot?start=u'.$user->id)
            && $buttons->contains(fn (array $button) => ($button['text'] ?? '') === 'Premium description'
                && str_contains((string) data_get($button, 'web_app.url'), '/tg/premium'));
    });
});
