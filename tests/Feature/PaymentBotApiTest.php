<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.payment_bot.api_secret' => 'test-payment-bot-secret',
    ]);
});

it('rejects payment bot api calls without a valid secret', function () {
    $this->getJson('/api/payment-bot/quote?telegram_id=123')
        ->assertUnauthorized();
});

it('returns a premium quote for a telegram student', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '900100',
        'is_premium' => false,
    ]);

    $this->withHeader('X-Payment-Bot-Secret', 'test-payment-bot-secret')
        ->getJson('/api/payment-bot/quote?telegram_id=900100')
        ->assertOk()
        ->assertJsonPath('user_id', $user->id)
        ->assertJsonPath('telegram_id', '900100')
        ->assertJsonPath('is_premium', false)
        ->assertJsonPath('currency', 'ETB')
        ->assertJsonStructure(['amount', 'pitch_title', 'pitch_body', 'payment_instructions']);
});

it('creates and reuses a pending payment bot payment', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '900101',
        'is_premium' => false,
    ]);

    $first = $this->withToken('test-payment-bot-secret', 'Bearer')
        ->postJson('/api/payment-bot/payments', ['telegram_id' => '900101'])
        ->assertCreated()
        ->json();

    expect($first['provider'])->toBe(Payment::PROVIDER_PAYMENT_BOT)
        ->and($first['status'])->toBe(PaymentStatus::Pending->value)
        ->and($first['user_id'])->toBe($user->id);

    $second = $this->withHeader('Authorization', 'Bearer test-payment-bot-secret')
        ->postJson('/api/payment-bot/payments', ['telegram_id' => '900101'])
        ->assertOk()
        ->json();

    expect($second['id'])->toBe($first['id'])
        ->and(Payment::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('verifies a payment bot payment idempotently and grants premium', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '900102',
        'is_premium' => false,
    ]);

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'provider' => Payment::PROVIDER_PAYMENT_BOT,
        'status' => PaymentStatus::Pending,
    ]);

    $this->withHeader('X-Payment-Bot-Secret', 'test-payment-bot-secret')
        ->postJson("/api/payment-bot/payments/{$payment->id}/verify")
        ->assertOk()
        ->assertJsonPath('status', PaymentStatus::Verified->value)
        ->assertJsonPath('is_premium', true);

    expect($user->fresh()->hasActivePremium())->toBeTrue()
        ->and($payment->fresh()->meta['verified_via'] ?? null)->toBe('payment_bot');

    $this->withHeader('X-Payment-Bot-Secret', 'test-payment-bot-secret')
        ->postJson("/api/payment-bot/payments/{$payment->id}/verify")
        ->assertOk()
        ->assertJsonPath('status', PaymentStatus::Verified->value);

    expect(Payment::query()->where('user_id', $user->id)->where('status', PaymentStatus::Verified)->count())->toBe(1);
});

it('rejects a pending payment bot payment', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '900103',
        'is_premium' => false,
    ]);

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'provider' => Payment::PROVIDER_PAYMENT_BOT,
        'status' => PaymentStatus::Pending,
    ]);

    $this->withHeader('X-Payment-Bot-Secret', 'test-payment-bot-secret')
        ->postJson("/api/payment-bot/payments/{$payment->id}/reject")
        ->assertOk()
        ->assertJsonPath('status', PaymentStatus::Rejected->value)
        ->assertJsonPath('is_premium', false);

    expect($user->fresh()->hasActivePremium())->toBeFalse()
        ->and($payment->fresh()->meta['verified_via'] ?? null)->toBe('payment_bot');
});
