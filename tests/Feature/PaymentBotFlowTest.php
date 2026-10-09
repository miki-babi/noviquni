<?php

use App\Enums\OnboardingStep;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.payment_bot.token' => 'payment-test-token',
        'services.telegram.file_admin_username' => 'payadmin',
    ]);

    Http::fake([
        'api.telegram.org/botpayment-test-token/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);
});

it('creates a pending payment and sends instructions on start deep link', function () {
    $student = User::factory()->student()->create([
        'telegram_id' => '700001',
        'onboarding_step' => OnboardingStep::Complete,
        'is_premium' => false,
    ]);

    $this->postJson('/telegram/payment-bot/webhook', [
        'update_id' => 1,
        'message' => [
            'message_id' => 10,
            'from' => [
                'id' => 700001,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'chat' => ['id' => 700001],
            'text' => '/start u'.$student->id,
        ],
    ])->assertOk();

    $payment = Payment::query()->where('user_id', $student->id)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->provider)->toBe(Payment::PROVIDER_PAYMENT_BOT)
        ->and($payment->status)->toBe(PaymentStatus::Pending);

    Http::assertSent(function ($request) use ($payment) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) ($request['text'] ?? '');

        return str_contains($text, (string) $payment->external_ref)
            && str_contains($text, 'photo');
    });
});

it('forwards a receipt photo to file admins with approve and reject buttons', function () {
    $student = User::factory()->student()->create([
        'telegram_id' => '700002',
        'name' => 'Student Two',
        'telegram_username' => 'studenttwo',
        'onboarding_step' => OnboardingStep::Complete,
        'is_premium' => false,
    ]);

    User::factory()->student()->create([
        'telegram_id' => '800001',
        'telegram_username' => 'payadmin',
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $this->postJson('/telegram/payment-bot/webhook', [
        'update_id' => 2,
        'message' => [
            'message_id' => 11,
            'from' => [
                'id' => 700002,
                'first_name' => 'Student',
                'username' => 'studenttwo',
            ],
            'chat' => ['id' => 700002],
            'photo' => [
                ['file_id' => 'small', 'width' => 90, 'height' => 90],
                ['file_id' => 'receipt_file_large', 'width' => 800, 'height' => 800],
            ],
        ],
    ])->assertOk();

    $payment = Payment::query()->where('user_id', $student->id)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->meta['receipt_file_id'] ?? null)->toBe('receipt_file_large');

    Http::assertSent(function ($request) use ($payment) {
        if (! str_contains($request->url(), '/sendPhoto')) {
            return false;
        }

        return ($request['chat_id'] ?? null) == '800001'
            && ($request['photo'] ?? null) === 'receipt_file_large'
            && str_contains((string) ($request['caption'] ?? ''), (string) $payment->external_ref)
            && str_contains(json_encode($request['reply_markup'] ?? []), 'pay:v:'.$payment->id)
            && str_contains(json_encode($request['reply_markup'] ?? []), 'pay:r:'.$payment->id);
    });
});

it('grants premium when a file admin approves', function () {
    $student = User::factory()->student()->create([
        'telegram_id' => '700003',
        'onboarding_step' => OnboardingStep::Complete,
        'is_premium' => false,
    ]);

    $payment = Payment::factory()->create([
        'user_id' => $student->id,
        'provider' => Payment::PROVIDER_PAYMENT_BOT,
        'status' => PaymentStatus::Pending,
        'external_ref' => 'PAY-APPROVE1',
    ]);

    $this->postJson('/telegram/payment-bot/webhook', [
        'update_id' => 3,
        'callback_query' => [
            'id' => 'cb-approve',
            'data' => 'pay:v:'.$payment->id,
            'from' => [
                'id' => 800001,
                'username' => 'payadmin',
            ],
            'message' => [
                'message_id' => 50,
                'chat' => ['id' => 800001],
                'caption' => 'Premium payment screenshot',
            ],
        ],
    ])->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Verified)
        ->and($student->fresh()->hasActivePremium())->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && ($request['chat_id'] ?? null) == '700003'
            && str_contains((string) ($request['text'] ?? ''), 'approved');
    });
});

it('rejects a payment and notifies the student', function () {
    $student = User::factory()->student()->create([
        'telegram_id' => '700004',
        'onboarding_step' => OnboardingStep::Complete,
        'is_premium' => false,
    ]);

    $payment = Payment::factory()->create([
        'user_id' => $student->id,
        'provider' => Payment::PROVIDER_PAYMENT_BOT,
        'status' => PaymentStatus::Pending,
        'external_ref' => 'PAY-REJECT1',
    ]);

    $this->postJson('/telegram/payment-bot/webhook', [
        'update_id' => 4,
        'callback_query' => [
            'id' => 'cb-reject',
            'data' => 'pay:r:'.$payment->id,
            'from' => [
                'id' => 800001,
                'username' => 'payadmin',
            ],
            'message' => [
                'message_id' => 51,
                'chat' => ['id' => 800001],
                'caption' => 'Premium payment screenshot',
            ],
        ],
    ])->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($student->fresh()->hasActivePremium())->toBeFalse();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && ($request['chat_id'] ?? null) == '700004'
            && str_contains((string) ($request['text'] ?? ''), 'not approved');
    });
});

it('reverts premium when a file admin undoes an approval', function () {
    $student = User::factory()->student()->create([
        'telegram_id' => '700006',
        'onboarding_step' => OnboardingStep::Complete,
        'is_premium' => false,
    ]);

    $payment = Payment::factory()->create([
        'user_id' => $student->id,
        'provider' => Payment::PROVIDER_PAYMENT_BOT,
        'status' => PaymentStatus::Pending,
        'external_ref' => 'PAY-UNDO1',
    ]);

    app(PaymentService::class)->verifyViaPaymentBot($payment);

    expect($student->fresh()->hasActivePremium())->toBeTrue();

    $this->postJson('/telegram/payment-bot/webhook', [
        'update_id' => 6,
        'callback_query' => [
            'id' => 'cb-undo',
            'data' => 'pay:u:'.$payment->id,
            'from' => [
                'id' => 800001,
                'username' => 'payadmin',
            ],
            'message' => [
                'message_id' => 53,
                'chat' => ['id' => 800001],
                'caption' => 'Premium payment screenshot',
            ],
        ],
    ])->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($student->fresh()->hasActivePremium())->toBeFalse();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && ($request['chat_id'] ?? null) == '700006'
            && str_contains((string) ($request['text'] ?? ''), 'reverted');
    });
});

it('ignores approve callbacks from non file admins', function () {
    $student = User::factory()->student()->create([
        'telegram_id' => '700005',
        'onboarding_step' => OnboardingStep::Complete,
        'is_premium' => false,
    ]);

    $payment = Payment::factory()->create([
        'user_id' => $student->id,
        'provider' => Payment::PROVIDER_PAYMENT_BOT,
        'status' => PaymentStatus::Pending,
    ]);

    $this->postJson('/telegram/payment-bot/webhook', [
        'update_id' => 5,
        'callback_query' => [
            'id' => 'cb-denied',
            'data' => 'pay:v:'.$payment->id,
            'from' => [
                'id' => 900001,
                'username' => 'randomuser',
            ],
            'message' => [
                'message_id' => 52,
                'chat' => ['id' => 900001],
                'caption' => 'Premium payment screenshot',
            ],
        ],
    ])->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($student->fresh()->hasActivePremium())->toBeFalse();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/answerCallbackQuery')
            && str_contains((string) ($request['text'] ?? ''), 'Not authorized');
    });
});
