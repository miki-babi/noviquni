<?php

use App\Enums\GuidanceRequestStatus;
use App\Enums\OnboardingStep;
use App\Filament\Actions\SendGuidanceRequestTelegramMessageAction;
use App\Filament\Resources\OpportunityGuidanceRequests\Pages\ListOpportunityGuidanceRequests;
use App\Filament\Resources\OpportunityGuidanceRequests\Pages\ViewOpportunityGuidanceRequest;
use App\Models\NotificationDelivery;
use App\Models\Opportunity;
use App\Models\OpportunityGuidanceRequest;
use App\Models\User;
use App\Services\OpportunityGuidanceService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.bot_username' => 'noviquni_bot',
    ]);
});

it('shows guidance request details on the view page', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $student = User::factory()->student()->create([
        'name' => 'Detail Student',
        'telegram_username' => 'detail_student',
        'telegram_id' => '999101',
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $opportunity = Opportunity::factory()->published()->job()->verifiedPartner('Detail Partner')->create([
        'title' => 'Detail Opportunity',
    ]);

    $request = OpportunityGuidanceRequest::factory()->create([
        'user_id' => $student->id,
        'opportunity_id' => $opportunity->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewOpportunityGuidanceRequest::class, ['record' => $request->getRouteKey()])
        ->assertOk()
        ->assertSee('Detail Student')
        ->assertSee('Detail Opportunity')
        ->assertSee('Detail Partner')
        ->assertSee('@detail_student');
});

it('prefills the send message form with guidance request details', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $student = User::factory()->student()->create([
        'name' => 'Prefill Student',
        'telegram_id' => '999201',
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $assignee = User::factory()->student()->create([
        'name' => 'Prefill Guide',
        'telegram_username' => 'prefill_guide',
    ]);

    $opportunity = Opportunity::factory()->published()->job()->verifiedPartner('Prefill Partner')->create([
        'title' => 'Prefill Opportunity',
    ]);

    $request = OpportunityGuidanceRequest::factory()->assigned($assignee)->create([
        'user_id' => $student->id,
        'opportunity_id' => $opportunity->id,
    ]);

    $this->actingAs($admin);

    $expectedBody = SendGuidanceRequestTelegramMessageAction::defaultBody($request);

    Livewire::test(ViewOpportunityGuidanceRequest::class, ['record' => $request->getRouteKey()])
        ->mountAction('sendTelegramMessage')
        ->assertActionDataSet([
            'body' => $expectedBody,
        ]);

    expect($expectedBody)
        ->toContain('Prefill Opportunity')
        ->toContain('Prefill Partner')
        ->toContain('Status: assigned')
        ->toContain('Assigned guide: Prefill Guide (@prefill_guide)');
});

it('sends a telegram message to the student from the guidance request view page', function () {
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 601]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $student = User::factory()->student()->create([
        'name' => 'Message Student',
        'telegram_id' => '999202',
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $opportunity = Opportunity::factory()->published()->job()->verifiedPartner('Msg Partner')->create([
        'title' => 'Message Opportunity',
    ]);

    $request = OpportunityGuidanceRequest::factory()->create([
        'user_id' => $student->id,
        'opportunity_id' => $opportunity->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewOpportunityGuidanceRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('sendTelegramMessage', [
            'body' => 'Hello {{first_name}} about your guidance request',
            'buttons' => [],
        ])
        ->assertNotified();

    expect(NotificationDelivery::query()->where('user_id', $student->id)->exists())->toBeTrue();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return (string) ($data['chat_id'] ?? '') === '999202'
            && ($data['text'] ?? '') === 'Hello Message about your guidance request';
    });
});

it('lets an admin delete a guidance request from the list', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $student = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_premium' => true,
    ]);

    $opportunity = Opportunity::factory()->published()->job()->guidanceAvailable()->create([
        'title' => 'Delete Me Opportunity',
    ]);

    $request = OpportunityGuidanceRequest::factory()->create([
        'user_id' => $student->id,
        'opportunity_id' => $opportunity->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListOpportunityGuidanceRequests::class)
        ->callTableAction('delete', $request)
        ->assertNotified();

    expect(OpportunityGuidanceRequest::query()->whereKey($request->id)->exists())->toBeFalse();

    config(['services.telegram.premium_enabled' => true]);

    app(OpportunityGuidanceService::class)->requestGuidance($student, $opportunity);

    expect(OpportunityGuidanceRequest::query()->where('user_id', $student->id)->where('opportunity_id', $opportunity->id)->exists())->toBeTrue();
});

it('assigns a guide from the guidance request list', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $student = User::factory()->student()->create([
        'telegram_id' => '999303',
        'telegram_username' => 'list_student',
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $assignee = User::factory()->student()->create([
        'telegram_username' => 'list_guide',
        'name' => 'List Guide',
    ]);

    $opportunity = Opportunity::factory()->published()->job()->verifiedPartner('List Partner')->create([
        'title' => 'List Opportunity',
    ]);

    $request = OpportunityGuidanceRequest::factory()->create([
        'user_id' => $student->id,
        'opportunity_id' => $opportunity->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListOpportunityGuidanceRequests::class)
        ->callTableAction('assign', $request, [
            'assigned_to_user_id' => $assignee->id,
        ])
        ->assertNotified();

    $request->refresh();

    expect($request->status)->toBe(GuidanceRequestStatus::Assigned)
        ->and($request->assigned_to_user_id)->toBe($assignee->id);
});
