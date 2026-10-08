<?php

use App\Enums\OnboardingStep;
use App\Enums\PaymentStatus;
use App\Jobs\ProcessVerifyCheckoutWebhookJob;
use App\Models\Payment;
use App\Models\User;
use App\Models\VerifyCheckoutWebhookEvent;
use App\Services\PaymentService;
use App\Services\SettingsService;
use App\Services\VerifyCheckout\VerifyCheckoutClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();

    config([
        'services.telegram.premium_enabled' => true,
        'services.verify_checkout.api_key' => 'vchk_test_key',
        'services.verify_checkout.webhook_secret' => 'whsec_test_secret',
        'services.verify_checkout.api_version' => '2026-06-01',
        'app.url' => 'https://noviquni.test',
    ]);
});

function fakeDeposit(array $overrides = []): array
{
    return array_merge([
        'id' => 'dep_test_1',
        'merchant_order_id' => 'order_1',
        'merchant_customer_id' => 'user_1',
        'status' => 'awaiting_transfer',
        'amount' => '30.00',
        'currency' => 'ETB',
        'checkout_url' => 'https://checkout.verify.et/c/test_token',
        'support_reference' => 'VC-1001',
        'expires_at' => now()->addHour()->toIso8601String(),
        'created_at' => now()->toIso8601String(),
    ], $overrides);
}

function signVerifyCheckoutWebhook(string $rawBody, ?string $timestamp = null): array
{
    $timestamp ??= (string) time();
    $signature = 'v1='.hash_hmac('sha256', $timestamp.'.'.$rawBody, 'whsec_test_secret');

    return [
        'VerifyCheckout-Timestamp' => $timestamp,
        'VerifyCheckout-Signature' => $signature,
        'VerifyCheckout-Api-Version' => '2026-06-01',
        'Content-Type' => 'application/json',
    ];
}

it('starts verify checkout and redirects to the hosted checkout url', function () {
    Http::fake([
        'checkoutapi.verify.et/v1/deposits' => Http::response([
            'data' => fakeDeposit(['merchant_customer_id' => 'user_will_replace']),
            'meta' => ['requestId' => 'req_1', 'apiVersion' => '2026-06-01'],
        ], 201),
    ]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $this->actingAs($user)
        ->post(route('tg.premium.pay'))
        ->assertRedirect('https://checkout.verify.et/c/test_token');

    $payment = Payment::query()->where('user_id', $user->id)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->provider)->toBe(Payment::PROVIDER_VERIFY_CHECKOUT)
        ->and($payment->deposit_id)->toBe('dep_test_1')
        ->and($payment->idempotency_key)->not->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::Pending);

    Http::assertSent(function ($request) use ($payment, $user) {
        return $request->url() === 'https://checkoutapi.verify.et/v1/deposits'
            && $request->hasHeader('Authorization', 'Bearer vchk_test_key')
            && $request->hasHeader('VerifyCheckout-Version', '2026-06-01')
            && $request->hasHeader('Idempotency-Key', $payment->idempotency_key)
            && $request['merchant_customer_id'] === 'user_'.$user->id
            && $request['amount'] === '30.00'
            && $request['currency'] === 'ETB';
    });
});

it('reuses the same idempotency key for an open verify checkout attempt', function () {
    Http::fake([
        'checkoutapi.verify.et/v1/deposits' => Http::sequence()
            ->push([
                'data' => fakeDeposit(),
                'meta' => ['requestId' => 'req_1', 'apiVersion' => '2026-06-01'],
            ], 201)
            ->push([
                'data' => fakeDeposit(['status' => 'awaiting_transfer']),
                'meta' => ['requestId' => 'req_2', 'apiVersion' => '2026-06-01'],
            ], 200),
    ]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $first = app(PaymentService::class)->startVerifyCheckoutPremium($user);
    $second = app(PaymentService::class)->startVerifyCheckoutPremium($user);

    expect(Payment::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($second->idempotency_key)->toBe($first->idempotency_key)
        ->and($second->checkoutUrl)->toBe('https://checkout.verify.et/c/test_token');

    Http::assertSentCount(2);
});

it('fulfills premium exactly once from a succeeded webhook', function () {
    Queue::fake();

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'provider' => Payment::PROVIDER_VERIFY_CHECKOUT,
        'deposit_id' => 'dep_success_1',
        'idempotency_key' => 'checkout_test_success',
        'deposit_status' => 'awaiting_transfer',
        'status' => PaymentStatus::Pending,
        'amount' => 30,
    ]);

    Http::fake([
        'checkoutapi.verify.et/v1/deposits/dep_success_1' => Http::response([
            'data' => fakeDeposit([
                'id' => 'dep_success_1',
                'status' => 'succeeded',
                'amount' => '30.00',
            ]),
            'meta' => ['requestId' => 'req_ok', 'apiVersion' => '2026-06-01'],
        ], 200),
    ]);

    $payload = [
        'id' => 'evt_success_1',
        'type' => 'deposit.succeeded',
        'api_version' => '2026-06-01',
        'sequence' => 5,
        'created_at' => now()->toIso8601String(),
        'data' => [
            'object' => [
                'id' => 'dep_success_1',
                'merchant_order_id' => 'order_1',
                'environment' => 'live',
                'status' => 'succeeded',
                'currency' => 'ETB',
                'amount' => '30.00',
                'metadata' => [],
                'created_at' => now()->toIso8601String(),
            ],
        ],
    ];

    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $headers = signVerifyCheckoutWebhook($raw);
    $headers['VerifyCheckout-Event-Id'] = 'evt_success_1';
    $headers['VerifyCheckout-Event'] = 'deposit.succeeded';
    $headers['VerifyCheckout-Delivery-Id'] = 'dlv_1';

    $this->call(
        'POST',
        route('webhooks.verify-checkout'),
        [],
        [],
        [],
        $this->transformHeadersToServerVars($headers),
        $raw,
    )->assertOk();

    expect(VerifyCheckoutWebhookEvent::query()->where('event_id', 'evt_success_1')->exists())->toBeTrue();
    Queue::assertPushed(ProcessVerifyCheckoutWebhookJob::class, 1);

    $event = VerifyCheckoutWebhookEvent::query()->where('event_id', 'evt_success_1')->first();
    (new ProcessVerifyCheckoutWebhookJob($event->id))->handle(
        app(PaymentService::class),
        app(VerifyCheckoutClient::class),
    );

    expect($payment->fresh()->status)->toBe(PaymentStatus::Verified)
        ->and($payment->fresh()->fulfilled_at)->not->toBeNull()
        ->and($user->fresh()->hasActivePremium())->toBeTrue();

    (new ProcessVerifyCheckoutWebhookJob($event->id))->handle(
        app(PaymentService::class),
        app(VerifyCheckoutClient::class),
    );

    expect($user->subscriptions()->count())->toBe(1);
});

it('rejects webhooks with invalid signatures', function () {
    $payload = json_encode([
        'id' => 'evt_bad',
        'type' => 'deposit.succeeded',
        'data' => ['object' => ['id' => 'dep_x', 'status' => 'succeeded']],
    ], JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        route('webhooks.verify-checkout'),
        [],
        [],
        [],
        $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'VerifyCheckout-Timestamp' => (string) time(),
            'VerifyCheckout-Signature' => 'v1='.str_repeat('a', 64),
            'VerifyCheckout-Api-Version' => '2026-06-01',
        ]),
        $payload,
    )->assertUnauthorized();

    expect(VerifyCheckoutWebhookEvent::query()->count())->toBe(0);
});

it('shows payment status on the signed return page without granting from the redirect alone', function () {
    $user = User::factory()->student()->create();

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'provider' => Payment::PROVIDER_VERIFY_CHECKOUT,
        'deposit_id' => 'dep_pending_return',
        'deposit_status' => 'verification_pending',
        'status' => PaymentStatus::Pending,
    ]);

    Http::fake([
        'checkoutapi.verify.et/v1/deposits/dep_pending_return' => Http::response([
            'data' => fakeDeposit([
                'id' => 'dep_pending_return',
                'status' => 'verification_pending',
            ]),
            'meta' => ['requestId' => 'req_pending', 'apiVersion' => '2026-06-01'],
        ], 200),
    ]);

    $url = URL::signedRoute('tg.premium.return', ['payment' => $payment->id]);

    $this->get($url)
        ->assertOk()
        ->assertSee('verification_pending')
        ->assertSee('Checking your payment', false);

    expect($user->fresh()->hasActivePremium())->toBeFalse()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});
