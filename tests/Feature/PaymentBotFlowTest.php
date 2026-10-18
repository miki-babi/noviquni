<?php

use App\Enums\OnboardingStep;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentBotTelegramService;
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

function paymentBotEncodeMarkup(mixed $replyMarkup): string
{
    $encoded = is_string($replyMarkup)
        ? $replyMarkup
        : json_encode($replyMarkup, JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? $encoded : '';
}

function paymentBotHasMainKeyboard(mixed $replyMarkup): bool
{
    $encoded = paymentBotEncodeMarkup($replyMarkup);

    if ($encoded === '') {
        return false;
    }

    $registerPos = strpos($encoded, PaymentBotTelegramService::BUTTON_REGISTER);
    $contactPos = strpos($encoded, PaymentBotTelegramService::BUTTON_CONTACT_US);

    return $registerPos !== false
        && $contactPos !== false
        && $registerPos < $contactPos
        && str_contains($encoded, '"style":"primary"')
        && ! str_contains($encoded, PaymentBotTelegramService::BUTTON_WHAT_YOU_GET)
        && ! str_contains($encoded, 'Our story')
        && ! str_contains($encoded, 'Our mission');
}

function paymentBotHasWhatYouGetWebApp(mixed $replyMarkup): bool
{
    $encoded = paymentBotEncodeMarkup($replyMarkup);

    return $encoded !== ''
        && str_contains($encoded, PaymentBotTelegramService::BUTTON_WHAT_YOU_GET)
        && str_contains($encoded, 'web_app')
        && str_contains($encoded, '/tg/premium');
}

function paymentBotRequestHasWhatYouGetWebApp($request): bool
{
    return str_contains($request->url(), '/sendMessage')
        && paymentBotHasWhatYouGetWebApp($request['reply_markup'] ?? null);
}

function paymentBotHasPaymentCardInlineKeyboard(mixed $replyMarkup): bool
{
    $encoded = paymentBotEncodeMarkup($replyMarkup);

    if ($encoded === '') {
        return false;
    }

    return str_contains($encoded, PaymentBotTelegramService::BUTTON_SEND_SCREENSHOT)
        && str_contains($encoded, PaymentBotTelegramService::BUTTON_CBE)
        && str_contains($encoded, PaymentBotTelegramService::BUTTON_TELEBIRR)
        && str_contains($encoded, PaymentBotTelegramService::BUTTON_ABOUT_PREMIUM)
        && str_contains($encoded, 'pay:screenshot')
        && str_contains($encoded, 'pay:cbe')
        && str_contains($encoded, 'pay:telebirr')
        && str_contains($encoded, '"text":"'.PaymentBotTelegramService::BUTTON_CBE.'","callback_data":"pay:cbe","style":"success"')
        && str_contains($encoded, '"text":"'.PaymentBotTelegramService::BUTTON_ABOUT_PREMIUM.'","web_app":{"url":"')
        && str_contains($encoded, '"/tg/premium"')
        && str_contains($encoded, '"style":"primary"');
}

function paymentBotHasTelebirrCardInlineKeyboard(mixed $replyMarkup): bool
{
    $encoded = paymentBotEncodeMarkup($replyMarkup);

    if ($encoded === '') {
        return false;
    }

    return str_contains($encoded, PaymentBotTelegramService::BUTTON_SEND_SCREENSHOT)
        && str_contains($encoded, PaymentBotTelegramService::BUTTON_CBE)
        && str_contains($encoded, PaymentBotTelegramService::BUTTON_TELEBIRR)
        && str_contains($encoded, PaymentBotTelegramService::BUTTON_ABOUT_PREMIUM)
        && str_contains($encoded, 'pay:screenshot')
        && str_contains($encoded, 'pay:cbe')
        && str_contains($encoded, 'pay:telebirr')
        && str_contains($encoded, '"text":"'.PaymentBotTelegramService::BUTTON_TELEBIRR.'","callback_data":"pay:telebirr","style":"success"')
        && str_contains($encoded, '"text":"'.PaymentBotTelegramService::BUTTON_ABOUT_PREMIUM.'","web_app":{"url":"')
        && str_contains($encoded, '"/tg/premium"')
        && str_contains($encoded, '"style":"primary"');
}

it('sends welcome with What you get Mini App button and refreshes reply keyboard on start', function () {
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

    expect(Payment::query()->where('user_id', $student->id)->exists())->toBeFalse();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) ($request['text'] ?? '');

        return str_contains($text, 'Welcome to NoviqUni Premium payments')
            && paymentBotHasWhatYouGetWebApp($request['reply_markup'] ?? null);
    });

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return paymentBotHasMainKeyboard($request['reply_markup'] ?? null);
    });
});

it('attaches What you get Mini App button when the old reply button is tapped', function () {
    $this->postJson('/telegram/payment-bot/webhook', [
        'update_id' => 10,
        'message' => [
            'message_id' => 20,
            'from' => [
                'id' => 700010,
                'first_name' => 'Guest',
            ],
            'chat' => ['id' => 700010],
            'text' => PaymentBotTelegramService::BUTTON_WHAT_YOU_GET,
        ],
    ])->assertOk();

    Http::assertSent(function ($request) {
        if (! paymentBotRequestHasWhatYouGetWebApp($request)) {
            return false;
        }

        $text = (string) ($request['text'] ?? '');

        return str_contains($text, 'What you get')
            && str_contains($text, 'Premium benefits');
    });
});

it('sends a contact link to @mikiyas_sh when Contact us is tapped', function () {
    $this->postJson('/telegram/payment-bot/webhook', [
        'update_id' => 11,
        'message' => [
            'message_id' => 21,
            'from' => [
                'id' => 700019,
                'first_name' => 'Guest',
            ],
            'chat' => ['id' => 700019],
            'text' => PaymentBotTelegramService::BUTTON_CONTACT_US,
        ],
    ])->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) ($request['text'] ?? '');
        $markup = paymentBotEncodeMarkup($request['reply_markup'] ?? null);

        return str_contains($text, 'mikiyas_sh')
            && str_contains($markup, 'mikiyas_sh')
            && str_contains($markup, 'https://t.me/mikiyas_sh')
            && str_contains($markup, 'url');
    });
});

it('creates a pending payment and sends the CBE-style briefing when Register now is tapped', function () {
    $student = User::factory()->student()->create([
        'telegram_id' => '700011',
        'onboarding_step' => OnboardingStep::Complete,
        'is_premium' => false,
    ]);

    $this->postJson('/telegram/payment-bot/webhook', [
        'update_id' => 11,
        'message' => [
            'message_id' => 21,
            'from' => [
                'id' => 700011,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'chat' => ['id' => 700011],
            'text' => PaymentBotTelegramService::BUTTON_REGISTER,
        ],
    ])->assertOk();

    $payment = Payment::query()->where('user_id', $student->id)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->provider)->toBe(Payment::PROVIDER_PAYMENT_BOT)
        ->and($payment->status)->toBe(PaymentStatus::Pending);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) ($request['text'] ?? '');

        return str_contains($text, 'CBE')
            && str_contains($text, '1000518046822')
            && str_contains($text, 'Mikiyas Shiferaw')
            && str_contains($text, '500')
            && str_contains($text, 'ETB')
            && paymentBotHasPaymentCardInlineKeyboard($request['reply_markup'] ?? null);
    });
});

it('tells unlinked users to open the main bot when Register now is tapped', function () {
    $this->postJson('/telegram/payment-bot/webhook', [
        'update_id' => 12,
        'message' => [
            'message_id' => 22,
            'from' => [
                'id' => 700099,
                'first_name' => 'Unknown',
            ],
            'chat' => ['id' => 700099],
            'text' => PaymentBotTelegramService::BUTTON_REGISTER,
        ],
    ])->assertOk();

    expect(Payment::query()->count())->toBe(0);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) ($request['text'] ?? '');

        return str_contains($text, 'main NoviqUni bot')
            && str_contains($text, 'Become Premium')
            && paymentBotHasWhatYouGetWebApp($request['reply_markup'] ?? null);
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

    Http::assertSent(function ($request) use ($payment) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        if (($request['chat_id'] ?? null) != '700004') {
            return false;
        }

        $text = (string) ($request['text'] ?? '');
        $keyboard = $request['reply_markup']['inline_keyboard'] ?? [];

        return str_contains($text, 'not approved')
            && str_contains($text, 'PAY-REJECT1')
            && data_get($keyboard, '0.0.text') === 'Try again'
            && data_get($keyboard, '0.0.callback_data') === 'pay:retry:'.$payment->id
            && data_get($keyboard, '1.0.text') === 'Contact us'
            && str_starts_with((string) data_get($keyboard, '1.0.url'), 'https://t.me/mikiyas_sh');
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

it('sends a screenshot hint when the Send Screenshot inline button is tapped', function () {
    $this->postJson('/telegram/payment-bot/webhook', [
        'update_id' => 20,
        'callback_query' => [
            'id' => 'cb-screenshot',
            'data' => 'pay:screenshot',
            'from' => [
                'id' => 700020,
                'username' => 'student',
            ],
            'message' => [
                'message_id' => 60,
                'chat' => ['id' => 700020],
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/answerCallbackQuery')
            && ($request['callback_query_id'] ?? null) === 'cb-screenshot';
    });

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && ($request['chat_id'] ?? null) == '700020'
            && str_contains((string) ($request['text'] ?? ''), 'Please send your payment screenshot here');
    });
});

it('edits the existing message to Telebirr details when the Telebirr inline button is tapped', function () {
    $this->postJson('/telegram/payment-bot/webhook', [
        'update_id' => 21,
        'callback_query' => [
            'id' => 'cb-telebirr',
            'data' => 'pay:telebirr',
            'from' => [
                'id' => 700021,
                'username' => 'student',
            ],
            'message' => [
                'message_id' => 61,
                'chat' => ['id' => 700021],
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/answerCallbackQuery')
            && ($request['callback_query_id'] ?? null) === 'cb-telebirr';
    });

    Http::assertSent(function ($request) {
        $text = (string) ($request['text'] ?? '');

        return str_contains($request->url(), '/editMessageText')
            && ($request['chat_id'] ?? null) == '700021'
            && ($request['message_id'] ?? null) == '61'
            && str_contains($text, 'Telebirr')
            && str_contains($text, '0967072576')
            && str_contains($text, 'Mikiyas Shiferaw')
            && str_contains($text, '500')
            && str_contains($text, 'ETB')
            && paymentBotHasTelebirrCardInlineKeyboard($request['reply_markup'] ?? null);
    });
});

it('edits the existing message back to CBE details when the CBE inline button is tapped', function () {
    $this->postJson('/telegram/payment-bot/webhook', [
        'update_id' => 22,
        'callback_query' => [
            'id' => 'cb-cbe',
            'data' => 'pay:cbe',
            'from' => [
                'id' => 700022,
                'username' => 'student',
            ],
            'message' => [
                'message_id' => 62,
                'chat' => ['id' => 700022],
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/answerCallbackQuery')
            && ($request['callback_query_id'] ?? null) === 'cb-cbe';
    });

    Http::assertSent(function ($request) {
        $text = (string) ($request['text'] ?? '');

        return str_contains($request->url(), '/editMessageText')
            && ($request['chat_id'] ?? null) == '700022'
            && ($request['message_id'] ?? null) == '62'
            && str_contains($text, 'CBE')
            && str_contains($text, '1000518046822')
            && str_contains($text, 'Mikiyas Shiferaw')
            && str_contains($text, '500')
            && str_contains($text, 'ETB')
            && paymentBotHasPaymentCardInlineKeyboard($request['reply_markup'] ?? null);
    });
});
