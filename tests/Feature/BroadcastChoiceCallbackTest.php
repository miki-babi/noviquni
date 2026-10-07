<?php

use App\Enums\BroadcastButtonType;
use App\Enums\OnboardingStep;
use App\Models\Broadcast;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.premium_enabled' => true,
    ]);
    Http::fake([
        'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true], 200),
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200),
        'api.telegram.org/bot*/editMessageText' => Http::response(['ok' => true, 'result' => ['message_id' => 50]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);
});

it('edits the broadcast message to the answer with a back button when a choice is tapped', function () {
    $user = User::factory()->student()->create([
        'name' => 'Ada Lovelace',
        'telegram_id' => '555902',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $broadcast = Broadcast::factory()->create([
        'buttons' => [
            [
                'label' => "I'm in",
                'type' => BroadcastButtonType::Choice->value,
                'response' => 'Awesome {{first_name}}, you are in!',
            ],
            [
                'label' => 'Not now',
                'type' => BroadcastButtonType::Choice->value,
                'response' => 'Okay {{first_name}}, maybe later.',
            ],
        ],
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9021,
        'callback_query' => [
            'id' => 'callback-9021',
            'data' => 'bcq:'.$broadcast->id.':0',
            'from' => [
                'id' => (int) $user->telegram_id,
                'first_name' => 'Ada',
                'username' => 'ada',
            ],
            'message' => [
                'message_id' => 50,
                'chat' => ['id' => (int) $user->telegram_id],
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) use ($broadcast) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        if (($request->data()['text'] ?? '') !== 'Awesome Ada, you are in!') {
            return false;
        }

        $markup = $request->data()['reply_markup'] ?? null;

        if (is_string($markup)) {
            $markup = json_decode($markup, true);
        }

        return ($markup['inline_keyboard'][0][0]['callback_data'] ?? null) === 'bcqb:'.$broadcast->id;
    });

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/sendMessage');
    });
});

it('restores the original broadcast message when back is tapped', function () {
    $user = User::factory()->student()->create([
        'name' => 'Ada Lovelace',
        'telegram_id' => '555903',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $broadcast = Broadcast::factory()->create([
        'body' => 'Hi {{first_name}}, are you joining?',
        'buttons' => [
            [
                'label' => "I'm in",
                'type' => BroadcastButtonType::Choice->value,
                'response' => 'You said yes.',
            ],
            [
                'label' => 'Not now',
                'type' => BroadcastButtonType::Choice->value,
                'response' => 'You said no.',
            ],
        ],
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9022,
        'callback_query' => [
            'id' => 'callback-9022',
            'data' => 'bcqb:'.$broadcast->id,
            'from' => [
                'id' => (int) $user->telegram_id,
                'first_name' => 'Ada',
                'username' => 'ada',
            ],
            'message' => [
                'message_id' => 50,
                'chat' => ['id' => (int) $user->telegram_id],
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) use ($broadcast) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        if (($request->data()['text'] ?? '') !== 'Hi Ada, are you joining?') {
            return false;
        }

        $markup = $request->data()['reply_markup'] ?? null;

        if (is_string($markup)) {
            $markup = json_decode($markup, true);
        }

        $row = $markup['inline_keyboard'][0] ?? [];

        return count($row) === 2
            && ($row[0]['callback_data'] ?? null) === 'bcq:'.$broadcast->id.':0'
            && ($row[1]['callback_data'] ?? null) === 'bcq:'.$broadcast->id.':1';
    });
});

it('edits the same message to the other answer when a new choice is tapped', function () {
    $user = User::factory()->student()->create([
        'name' => 'Ada Lovelace',
        'telegram_id' => '555904',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $broadcast = Broadcast::factory()->create([
        'buttons' => [
            [
                'label' => "I'm in",
                'type' => BroadcastButtonType::Choice->value,
                'response' => 'You said yes.',
            ],
            [
                'label' => 'Not now',
                'type' => BroadcastButtonType::Choice->value,
                'response' => 'You said no.',
            ],
        ],
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9023,
        'callback_query' => [
            'id' => 'callback-9023',
            'data' => 'bcq:'.$broadcast->id.':1',
            'from' => [
                'id' => (int) $user->telegram_id,
                'first_name' => 'Ada',
                'username' => 'ada',
            ],
            'message' => [
                'message_id' => 50,
                'chat' => ['id' => (int) $user->telegram_id],
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) use ($broadcast) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        if (($request->data()['text'] ?? '') !== 'You said no.') {
            return false;
        }

        $markup = $request->data()['reply_markup'] ?? null;

        if (is_string($markup)) {
            $markup = json_decode($markup, true);
        }

        return ($markup['inline_keyboard'][0][0]['callback_data'] ?? null) === 'bcqb:'.$broadcast->id;
    });
});

it('alerts on invalid choice callbacks', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555905',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9024,
        'callback_query' => [
            'id' => 'callback-9024',
            'data' => 'bcq:999999:0',
            'from' => [
                'id' => (int) $user->telegram_id,
                'first_name' => 'Ada',
                'username' => 'ada',
            ],
            'message' => [
                'message_id' => 50,
                'chat' => ['id' => (int) $user->telegram_id],
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/answerCallbackQuery')) {
            return false;
        }

        $text = (string) ($request->data()['text'] ?? '');

        return $text !== '' && ($request->data()['show_alert'] ?? false);
    });

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            || str_contains($request->url(), '/editMessageText');
    });
});
