<?php

use App\Enums\GuidanceRequestStatus;
use App\Enums\OnboardingStep;
use App\Enums\OpportunityType;
use App\Filament\Resources\Opportunities\Pages\CreateOpportunity;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Bookmark;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Opportunity;
use App\Models\OpportunityGuidanceRequest;
use App\Models\Referral;
use App\Models\Stream;
use App\Models\User;
use App\Services\OpportunityGuidanceService;
use App\Services\SettingsService;
use App\Services\TelegramDeepLink;
use Database\Seeders\OpportunitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => '123456:TEST_TOKEN',
        'services.telegram.bot_username' => 'noviquni_bot',
        'app.url' => 'https://noviquni.test',
    ]);
});

it('lets an admin create a published opportunity', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(CreateOpportunity::class)
        ->fillForm([
            'title' => 'Campus Scholarship Fund',
            'slug' => 'campus-scholarship-fund',
            'type' => OpportunityType::Scholarship->value,
            'description' => 'Support for freshman applicants.',
            'url' => 'https://example.com/scholarship',
            'deadline' => now()->addMonth()->toDateString(),
            'sort_order' => 1,
            'is_published' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $opportunity = Opportunity::query()->where('slug', 'campus-scholarship-fund')->first();

    expect($opportunity)->not->toBeNull()
        ->and($opportunity->type)->toBe(OpportunityType::Scholarship)
        ->and($opportunity->is_published)->toBeTrue();
});

it('lists published opportunities of the tapped career type in the bot', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    User::factory()->student()->create([
        'telegram_id' => '555200',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    Opportunity::factory()->published()->scholarship()->create([
        'title' => 'Visible Scholarship',
        'sort_order' => 1,
    ]);
    Opportunity::factory()->scholarship()->create([
        'title' => 'Draft Scholarship',
        'is_published' => false,
    ]);
    Opportunity::factory()->published()->internship()->create([
        'title' => 'Other Type Internship',
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555200, '🎓 Scholarship opportunities', 200))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Scholarships')
            && $buttons->contains(fn (array $button) => ($button['text'] ?? '') === 'Visible Scholarship')
            && $buttons->every(fn (array $button) => ($button['text'] ?? '') !== 'Draft Scholarship')
            && $buttons->every(fn (array $button) => ($button['text'] ?? '') !== 'Other Type Internship');
    });
});

it('saves and unsaves an opportunity from the bot detail keyboard', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555201',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $opportunity = Opportunity::factory()->published()->job()->create([
        'title' => 'Junior Analyst Role',
        'url' => 'https://example.com/job',
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555201, 'open_opp:'.$opportunity->id, 30, 201))
        ->assertOk();

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555201, 'save:opp:'.$opportunity->id, 30, 202))
        ->assertOk();

    expect(Bookmark::query()->where('user_id', $user->id)->whereMorphedTo('bookmarkable', $opportunity)->exists())->toBeTrue();

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555201, 'save:opp:'.$opportunity->id, 30, 203))
        ->assertOk();

    expect(Bookmark::query()->where('user_id', $user->id)->whereMorphedTo('bookmarkable', $opportunity)->exists())->toBeFalse();
});

it('isolates quick saved tabs by bookmarkable type', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555202',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $resource = LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Saved Notes Only',
    ]);
    $scholarship = Opportunity::factory()->published()->scholarship()->create([
        'title' => 'Saved Scholarship Only',
    ]);
    $job = Opportunity::factory()->published()->job()->create([
        'title' => 'Saved Job Only',
    ]);

    foreach ([$resource, $scholarship, $job] as $item) {
        Bookmark::query()->create([
            'user_id' => $user->id,
            'bookmarkable_type' => $item->getMorphClass(),
            'bookmarkable_id' => $item->id,
        ]);
    }

    $this->postJson('/telegram/webhook', telegramMessagePayload(555202, '🔖 Quick saved', 204))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $keyboard = collect(data_get($data, 'reply_markup.keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Choose a Quick saved category')
            && $keyboard->contains(fn (array $button) => ($button['text'] ?? '') === 'Resources')
            && $keyboard->contains(fn (array $button) => ($button['text'] ?? '') === 'Scholarships');
    });

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Quick saved — Resources')
            && $buttons->contains(fn (array $button) => ($button['text'] ?? '') === 'Saved Notes Only')
            && $buttons->every(fn (array $button) => ($button['text'] ?? '') !== 'Saved Scholarship Only')
            && $buttons->every(fn (array $button) => ($button['text'] ?? '') !== 'Saved Job Only');
    });

    $this->postJson('/telegram/webhook', telegramMessagePayload(555202, 'Scholarships', 205))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Quick saved — Scholarships')
            && $buttons->contains(fn (array $button) => ($button['text'] ?? '') === 'Saved Scholarship Only')
            && $buttons->every(fn (array $button) => ($button['text'] ?? '') !== 'Saved Notes Only')
            && $buttons->every(fn (array $button) => ($button['text'] ?? '') !== 'Saved Job Only');
    });
});

it('shows mini app quick saved tabs and opportunity detail save toggle', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $scholarship = Opportunity::factory()->published()->scholarship()->create([
        'title' => 'Mini App Scholarship',
        'slug' => 'mini-app-scholarship',
    ]);

    Bookmark::query()->create([
        'user_id' => $user->id,
        'bookmarkable_type' => $scholarship->getMorphClass(),
        'bookmarkable_id' => $scholarship->id,
    ]);

    $this->actingAs($user)
        ->get(route('tg.saved', ['tab' => 'scholarships']))
        ->assertOk()
        ->assertSee('Mini App Scholarship')
        ->assertSee(route('tg.opportunities.show', $scholarship), false);

    $this->actingAs($user)
        ->get(route('tg.opportunities.show', $scholarship))
        ->assertOk()
        ->assertSee('Mini App Scholarship')
        ->assertSee(route('tg.saved.opportunities.toggle', $scholarship), false);

    $this->actingAs($user)
        ->from(route('tg.opportunities.show', $scholarship))
        ->post(route('tg.saved.opportunities.toggle', $scholarship))
        ->assertRedirect(route('tg.opportunities.show', $scholarship));

    expect(Bookmark::query()->where('user_id', $user->id)->whereMorphedTo('bookmarkable', $scholarship)->exists())->toBeFalse();
});

it('keeps existing resource bookmarks after polymorphic migration shape', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);

    $resource = LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Legacy Resource Bookmark',
    ]);

    $bookmark = Bookmark::query()->create([
        'user_id' => $user->id,
        'bookmarkable_type' => $resource->getMorphClass(),
        'bookmarkable_id' => $resource->id,
    ]);

    expect($bookmark->fresh()->learningResource()?->is($resource))->toBeTrue()
        ->and($user->bookmarks()->whereMorphedTo('bookmarkable', $resource)->exists())->toBeTrue();
});

it('seeds at least two published opportunities per type', function () {
    $this->seed(OpportunitySeeder::class);

    foreach (OpportunityType::cases() as $type) {
        expect(
            Opportunity::query()->published()->ofType($type)->count()
        )->toBeGreaterThanOrEqual(2);
    }
});

it('shows verified partner badge and request guidance when guidance is available', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    User::factory()->student()->create([
        'telegram_id' => '555300',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $opportunity = Opportunity::factory()
        ->published()
        ->scholarship()
        ->verifiedPartner('Acme Foundation')
        ->guidanceAvailable()
        ->create([
            'title' => 'Verified Scholarship',
        ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555300, 'open_opp:'.$opportunity->id, 50, 300))
        ->assertOk();

    Http::assertSent(function ($request) use ($opportunity) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Verified NOViQ Uni partner: Acme Foundation')
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === 'opp:guidance:'.$opportunity->id);
    });
});

it('shows request guidance without a verified partner when guidance is available', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    User::factory()->student()->create([
        'telegram_id' => '555301',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $opportunity = Opportunity::factory()
        ->published()
        ->internship()
        ->guidanceAvailable()
        ->create([
            'title' => 'Open Internship',
        ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555301, 'open_opp:'.$opportunity->id, 50, 310))
        ->assertOk();

    Http::assertSent(function ($request) use ($opportunity) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);
        $text = (string) ($data['text'] ?? '');

        return ! str_contains($text, 'Verified NOViQ Uni partner:')
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === 'opp:guidance:'.$opportunity->id);
    });
});

it('hides request guidance when guidance is not available even for verified partners', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    User::factory()->student()->create([
        'telegram_id' => '555302',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $opportunity = Opportunity::factory()
        ->published()
        ->job()
        ->verifiedPartner('No Guidance Partner')
        ->create([
            'title' => 'Partner Job Without Guidance',
        ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555302, 'open_opp:'.$opportunity->id, 50, 311))
        ->assertOk();

    Http::assertSent(function ($request) use ($opportunity) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Verified NOViQ Uni partner: No Guidance Partner')
            && $buttons->doesntContain(fn (array $button) => ($button['callback_data'] ?? '') === 'opp:guidance:'.$opportunity->id);
    });
});

it('creates a pending guidance request and notifies admins without a student DM link', function () {
    Queue::fake();

    config(['services.telegram.file_admin_username' => 'noviqsupport']);

    User::factory()->student()->create([
        'telegram_id' => '888001',
        'telegram_username' => 'student_one',
        'name' => 'Student One',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    User::factory()->admin()->create([
        'telegram_id' => '888002',
        'telegram_username' => 'noviqsupport',
    ]);

    $opportunity = Opportunity::factory()
        ->published()
        ->job()
        ->guidanceAvailable()
        ->create([
            'title' => 'Guidance Job',
        ]);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(888001, 'opp:guidance:'.$opportunity->id, 51, 301))
        ->assertOk();

    expect(OpportunityGuidanceRequest::query()->count())->toBe(1);
    expect(OpportunityGuidanceRequest::query()->first()?->status)->toBe(GuidanceRequestStatus::Pending);

    Queue::assertPushed(SendTelegramMessageJob::class, 1);
    Queue::assertPushed(SendTelegramMessageJob::class, function (SendTelegramMessageJob $job): bool {
        return (string) $job->chatId === '888002'
            && ! str_contains($job->text, 't.me/')
            && data_get($job->payload, 'reply_markup') === null;
    });

    Http::assertNotSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $url = (string) data_get($request->data(), 'reply_markup.inline_keyboard.0.0.url', '');

        return str_contains($url, 'https://t.me/');
    });
});

it('notifies the student with an open chat link when a guide is assigned', function () {
    Queue::fake();

    app(SettingsService::class)->set(
        SettingsService::OPPORTUNITY_GUIDANCE_OPENING_MESSAGE,
        'Hello coach about {title} with {partner} from {student}',
    );

    $student = User::factory()->student()->create([
        'telegram_id' => '888021',
        'telegram_username' => 'student_one',
        'name' => 'Student One',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $assignee = User::factory()->student()->create([
        'telegram_username' => 'guidancecoach',
        'name' => 'Guidance Coach',
    ]);

    $opportunity = Opportunity::factory()
        ->published()
        ->job()
        ->verifiedPartner('Partner Co')
        ->guidanceAvailable()
        ->create([
            'title' => 'Guidance Job',
        ]);

    $request = OpportunityGuidanceRequest::factory()->create([
        'user_id' => $student->id,
        'opportunity_id' => $opportunity->id,
    ]);

    app(OpportunityGuidanceService::class)->assign($request, $assignee);

    $request->refresh();

    expect($request->status)->toBe(GuidanceRequestStatus::Assigned)
        ->and($request->assigned_to_user_id)->toBe($assignee->id)
        ->and($request->assigned_at)->not->toBeNull();

    Queue::assertPushed(SendTelegramMessageJob::class, 1);
    Queue::assertPushed(SendTelegramMessageJob::class, function (SendTelegramMessageJob $job) use ($student): bool {
        if ((string) $job->chatId !== (string) $student->telegram_id) {
            return false;
        }

        $url = urldecode((string) data_get($job->payload, 'reply_markup.inline_keyboard.0.0.url', ''));

        return str_contains($job->text, 'Guidance Job')
            && str_contains($url, 'https://t.me/guidancecoach?text=')
            && str_contains($url, 'Hello coach about Guidance Job with Partner Co from @student_one');
    });
});

it('uses opportunity opening message override when assigning a guide', function () {
    Queue::fake();

    app(SettingsService::class)->set(
        SettingsService::OPPORTUNITY_GUIDANCE_OPENING_MESSAGE,
        'Default message {title}',
    );

    $student = User::factory()->student()->create([
        'telegram_id' => '888022',
        'telegram_username' => 'override_student',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $assignee = User::factory()->student()->create([
        'telegram_username' => 'partnermentor',
    ]);

    $opportunity = Opportunity::factory()
        ->published()
        ->scholarship()
        ->verifiedPartner('Override Partner')
        ->guidanceAvailable()
        ->create([
            'title' => 'Override Scholarship',
            'guidance_opening_message' => 'Custom opener for {title} / {partner} / {student}',
        ]);

    $request = OpportunityGuidanceRequest::factory()->create([
        'user_id' => $student->id,
        'opportunity_id' => $opportunity->id,
    ]);

    app(OpportunityGuidanceService::class)->assign($request, $assignee);

    Queue::assertPushed(SendTelegramMessageJob::class, function (SendTelegramMessageJob $job): bool {
        $url = urldecode((string) data_get($job->payload, 'reply_markup.inline_keyboard.0.0.url', ''));

        return str_contains($url, 'https://t.me/partnermentor?text=')
            && str_contains($url, 'Custom opener for Override Scholarship / Override Partner / @override_student')
            && ! str_contains($url, 'Default message');
    });
});

it('rejects assigning a guide without a telegram username', function () {
    $student = User::factory()->student()->create([
        'telegram_id' => '888023',
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $assignee = User::factory()->student()->create([
        'telegram_username' => null,
    ]);

    $opportunity = Opportunity::factory()->published()->job()->verifiedPartner('Partner Co')->create();

    $request = OpportunityGuidanceRequest::factory()->create([
        'user_id' => $student->id,
        'opportunity_id' => $opportunity->id,
    ]);

    expect(fn () => app(OpportunityGuidanceService::class)->assign($request, $assignee))
        ->toThrow(InvalidArgumentException::class, 'Assignee must have a Telegram username.');
});

it('dedupes existing guidance requests and admin notifications', function () {
    Queue::fake();

    config(['services.telegram.file_admin_username' => 'noviqsupport']);

    User::factory()->student()->create([
        'telegram_id' => '888003',
        'telegram_username' => 'student_two',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    User::factory()->admin()->create([
        'telegram_id' => '888004',
        'telegram_username' => 'noviqsupport',
    ]);

    $opportunity = Opportunity::factory()->published()->internship()->guidanceAvailable()->create();

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(888003, 'opp:guidance:'.$opportunity->id, 52, 302))
        ->assertOk();

    $this->postJson('/telegram/webhook', telegramCallbackPayload(888003, 'opp:guidance:'.$opportunity->id, 53, 303))
        ->assertOk();

    expect(OpportunityGuidanceRequest::query()->count())->toBe(1);
    Queue::assertPushed(SendTelegramMessageJob::class, 1);
});

it('does not create another guidance request when one is already assigned', function () {
    Queue::fake();

    config(['services.telegram.file_admin_username' => 'noviqsupport']);

    $student = User::factory()->student()->create([
        'telegram_id' => '888033',
        'telegram_username' => 'student_assigned',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    User::factory()->admin()->create([
        'telegram_id' => '888034',
        'telegram_username' => 'noviqsupport',
    ]);

    $opportunity = Opportunity::factory()->published()->job()->guidanceAvailable()->create();

    OpportunityGuidanceRequest::factory()->assigned()->create([
        'user_id' => $student->id,
        'opportunity_id' => $opportunity->id,
    ]);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(888033, 'opp:guidance:'.$opportunity->id, 54, 304))
        ->assertOk();

    expect(OpportunityGuidanceRequest::query()->where('user_id', $student->id)->count())->toBe(1);
    Queue::assertNothingPushed();
});

it('hides the request guidance button when the student already requested', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $student = User::factory()->student()->create([
        'telegram_id' => '555303',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $opportunity = Opportunity::factory()
        ->published()
        ->scholarship()
        ->guidanceAvailable()
        ->create([
            'title' => 'Already Requested Scholarship',
        ]);

    OpportunityGuidanceRequest::factory()->create([
        'user_id' => $student->id,
        'opportunity_id' => $opportunity->id,
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555303, 'open_opp:'.$opportunity->id, 55, 305))
        ->assertOk();

    Http::assertSent(function ($request) use ($opportunity) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);
        $text = (string) ($data['text'] ?? '');

        return str_contains($text, 'Guidance requested')
            && $buttons->doesntContain(fn (array $button) => ($button['callback_data'] ?? '') === 'opp:guidance:'.$opportunity->id);
    });
});

it('accepts mini app guidance requests when guidance is available', function () {
    Queue::fake();

    config(['services.telegram.file_admin_username' => 'noviqsupport']);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    User::factory()->admin()->create([
        'telegram_id' => '888005',
        'telegram_username' => 'noviqsupport',
    ]);

    $opportunity = Opportunity::factory()->published()->mentorship()->guidanceAvailable()->create([
        'slug' => 'mentor-guidance-opportunity',
    ]);

    $this->actingAs($user)
        ->from(route('tg.opportunities.show', $opportunity))
        ->post(route('tg.opportunities.guidance', $opportunity))
        ->assertRedirect(route('tg.opportunities.show', $opportunity))
        ->assertSessionHas('status')
        ->assertSessionMissing('support_url');

    expect(OpportunityGuidanceRequest::query()->where('user_id', $user->id)->count())->toBe(1);
    Queue::assertPushed(SendTelegramMessageJob::class, 1);

    $this->actingAs($user)
        ->get(route('tg.opportunities.show', $opportunity))
        ->assertOk()
        ->assertSee('Guidance requested')
        ->assertDontSee(route('tg.opportunities.guidance', $opportunity), false);
});

it('rejects mini app guidance requests when guidance is not available', function () {
    Queue::fake();

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $opportunity = Opportunity::factory()
        ->published()
        ->job()
        ->verifiedPartner('Blocked Partner')
        ->create([
            'slug' => 'no-guidance-opportunity',
        ]);

    $this->actingAs($user)
        ->from(route('tg.opportunities.show', $opportunity))
        ->post(route('tg.opportunities.guidance', $opportunity))
        ->assertNotFound();

    expect(OpportunityGuidanceRequest::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('includes a share deep link with the sharer referral code on bot opportunity detail', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555400',
        'referral_code' => 'SHAREABC',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $opportunity = Opportunity::factory()->published()->scholarship()->create([
        'title' => 'Shareable Scholarship',
        'url' => 'https://example.com/share-scholarship',
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555400, 'open_opp:'.$opportunity->id, 60, 400))
        ->assertOk();

    $expectedStart = 'opp_'.$opportunity->id.'_'.$user->referral_code;

    Http::assertSent(function ($request) use ($expectedStart) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $buttons = collect(data_get($request->data(), 'reply_markup.inline_keyboard', []))->flatten(1);

        return $buttons->contains(function (array $button) use ($expectedStart): bool {
            $url = urldecode((string) ($button['url'] ?? ''));

            return ($button['text'] ?? '') === '📤 Share'
                && str_contains($url, 'start='.$expectedStart);
        });
    });
});

it('attributes a referral and opens opportunity detail for a new user via share deep link', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $referrer = User::factory()->student()->create([
        'referral_code' => 'REFSHARE1',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $opportunity = Opportunity::factory()->published()->internship()->create([
        'title' => 'Shared Internship Detail',
    ]);

    $this->postJson(
        '/telegram/webhook',
        telegramMessagePayload(555401, '/start opp_'.$opportunity->id.'_'.$referrer->referral_code, 401),
    )->assertOk();

    $newUser = User::query()->where('telegram_id', '555401')->first();

    expect($newUser)->not->toBeNull()
        ->and($newUser->onboarding_step)->not->toBe(OnboardingStep::Complete)
        ->and($newUser->referred_by_user_id)->toBe($referrer->id);

    expect(Referral::query()->where('referrer_id', $referrer->id)->where('referred_id', $newUser->id)->exists())->toBeTrue();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Shared Internship Detail')
            && $buttons->contains(fn (array $button) => str_starts_with((string) ($button['callback_data'] ?? ''), 'save:opp:'));
    });
});

it('prompts onboarding when an incomplete user tries to save a shared opportunity', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555402',
        'onboarding_step' => OnboardingStep::Year,
        'is_active' => true,
    ]);

    $opportunity = Opportunity::factory()->published()->job()->create([
        'title' => 'Gated Save Job',
        'url' => 'https://example.com/gated-job',
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555402, 'save:opp:'.$opportunity->id, 61, 402))
        ->assertOk();

    expect(Bookmark::query()->where('user_id', $user->id)->exists())->toBeFalse();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/answerCallbackQuery')) {
            return false;
        }

        return str_contains((string) data_get($request->data(), 'text'), 'Finish onboarding first');
    });

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage') && ! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        return str_contains((string) data_get($request->data(), 'text'), 'year');
    });
});

it('does not attribute a referral when an existing user opens an opportunity share link', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $referrer = User::factory()->student()->create([
        'referral_code' => 'EXISTREF1',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $existing = User::factory()->student()->create([
        'telegram_id' => '555404',
        'referred_by_user_id' => null,
        'onboarding_step' => OnboardingStep::Year,
        'is_active' => true,
    ]);

    $opportunity = Opportunity::factory()->published()->scholarship()->create([
        'title' => 'Existing User Shared Scholarship',
    ]);

    $this->postJson(
        '/telegram/webhook',
        telegramMessagePayload(555404, '/start opp_'.$opportunity->id.'_'.$referrer->referral_code, 404),
    )->assertOk();

    expect($existing->fresh()->referred_by_user_id)->toBeNull();
    expect(Referral::query()->where('referred_id', $existing->id)->exists())->toBeFalse();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return str_contains((string) data_get($request->data(), 'text'), 'Existing User Shared Scholarship');
    });
});

it('opens a shared opportunity for a complete user without overwriting an existing referrer', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $originalReferrer = User::factory()->student()->create([
        'referral_code' => 'ORIGREF01',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $otherReferrer = User::factory()->student()->create([
        'referral_code' => 'OTHERREF1',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555403',
        'referred_by_user_id' => $originalReferrer->id,
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $opportunity = Opportunity::factory()->published()->mentorship()->create([
        'title' => 'Complete User Shared Mentorship',
    ]);

    $this->postJson(
        '/telegram/webhook',
        telegramMessagePayload(555403, '/start opp_'.$opportunity->id.'_'.$otherReferrer->referral_code, 403),
    )->assertOk();

    expect($user->fresh()->referred_by_user_id)->toBe($originalReferrer->id);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return str_contains((string) data_get($request->data(), 'text'), 'Complete User Shared Mentorship');
    });
});

it('shows a share link on the mini app opportunity page and blocks save when onboarding is incomplete', function () {
    $user = User::factory()->student()->create([
        'referral_code' => 'MINISH01',
        'onboarding_step' => OnboardingStep::Year,
        'is_active' => true,
    ]);

    $opportunity = Opportunity::factory()->published()->scholarship()->create([
        'title' => 'Mini App Share Scholarship',
        'slug' => 'mini-app-share-scholarship',
    ]);

    $deepLink = app(TelegramDeepLink::class)->forOpportunity($opportunity->id, $user);

    $this->actingAs($user)
        ->get(route('tg.opportunities.show', $opportunity))
        ->assertOk()
        ->assertSee('Mini App Share Scholarship', false)
        ->assertSee('t.me/share/url', false)
        ->assertSee(rawurlencode($deepLink), false);

    $this->actingAs($user)
        ->from(route('tg.opportunities.show', $opportunity))
        ->post(route('tg.saved.opportunities.toggle', $opportunity))
        ->assertRedirect(route('tg.opportunities.show', $opportunity))
        ->assertSessionHas('status')
        ->assertSessionHas('show_onboarding_link');

    expect(Bookmark::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('blocks mini app guidance when onboarding is incomplete', function () {
    Queue::fake();

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Stream,
        'is_active' => true,
    ]);

    $opportunity = Opportunity::factory()
        ->published()
        ->job()
        ->guidanceAvailable()
        ->create([
            'slug' => 'blocked-guidance-opportunity',
        ]);

    $this->actingAs($user)
        ->from(route('tg.opportunities.show', $opportunity))
        ->post(route('tg.opportunities.guidance', $opportunity))
        ->assertRedirect(route('tg.opportunities.show', $opportunity))
        ->assertSessionHas('show_onboarding_link');

    Queue::assertNothingPushed();
});
