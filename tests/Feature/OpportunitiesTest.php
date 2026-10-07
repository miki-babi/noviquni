<?php

use App\Enums\OnboardingStep;
use App\Enums\OpportunityType;
use App\Filament\Resources\Opportunities\Pages\CreateOpportunity;
use App\Models\Bookmark;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Opportunity;
use App\Models\Stream;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\OpportunitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Quick saved — Resources')
            && $buttons->contains(fn (array $button) => ($button['text'] ?? '') === 'Saved Notes Only')
            && $buttons->every(fn (array $button) => ($button['text'] ?? '') !== 'Saved Scholarship Only')
            && $buttons->every(fn (array $button) => ($button['text'] ?? '') !== 'Saved Job Only');
    });

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555202, 'saved:tab:scholarships', 40, 205))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Scholarships')
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
