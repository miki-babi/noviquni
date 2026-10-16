<?php

namespace App\Services;

use App\Enums\TelegramButtonStyle;

class PaymentBotTelegramService
{
    public const BUTTON_WHAT_YOU_GET = 'What you get';

    public const BUTTON_CONTACT_US = 'Contact us';

    public const BUTTON_REGISTER = 'Register now';

    public const BUTTON_SEND_SCREENSHOT = '📎 Send ScreenShot';

    public const BUTTON_TELEBIRR = 'Telebirr';

    public const BUTTON_CBE = 'CBE';

    public const BUTTON_ABOUT_PREMIUM = 'About Premium';

    public function api(): TelegramBotApi
    {
        $token = config('services.payment_bot.token');

        return new TelegramBotApi(
            filled($token) ? (string) $token : null,
            'TELEGRAM_PAYMENT_BOT_TOKEN is not set.',
        );
    }

    public function isConfigured(): bool
    {
        return $this->api()->isConfigured();
    }

    /**
     * @return array{keyboard: list<list<array<string, mixed>>>, resize_keyboard: true, is_persistent: true}
     */
    public function mainKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    [
                        'text' => self::BUTTON_REGISTER,
                        'style' => TelegramButtonStyle::Primary->value,
                    ],
                ],
                [
                    ['text' => self::BUTTON_CONTACT_US],
                ],
            ],
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    /**
     * Inline “What you get” opens the Premium gate Mini App.
     *
     * @return array{inline_keyboard: list<list<array<string, mixed>>>}
     */
    public function whatYouGetInlineKeyboard(): array
    {
        return $this->inlineKeyboard([[
            [
                'text' => self::BUTTON_WHAT_YOU_GET,
                'web_app' => ['url' => app(TelegramService::class)->miniAppUrl('tg.premium')],
            ],
        ]]);
    }

    /**
     * Inline keyboard for the CBE-style premium payment card.
     *
     * @return array{inline_keyboard: list<list<array<string, mixed>>>}
     */
    public function paymentCardInlineKeyboard(): array
    {
        $premiumMiniAppUrl = app(TelegramService::class)->miniAppUrl('tg.premium');

        return $this->inlineKeyboard([
            [
                [
                    'text' => self::BUTTON_SEND_SCREENSHOT,
                    'callback_data' => 'pay:screenshot',
                ],
            ],
            [
                [
                    'text' => self::BUTTON_CBE,
                    'callback_data' => 'pay:cbe',
                    'style' => TelegramButtonStyle::Success->value,
                ],
                [
                    'text' => self::BUTTON_TELEBIRR,
                    'callback_data' => 'pay:telebirr',
                ],
            ],
            [
                [
                    'text' => self::BUTTON_ABOUT_PREMIUM,
                    'web_app' => ['url' => $premiumMiniAppUrl],
                    'style' => TelegramButtonStyle::Primary->value,
                ],
            ],
        ]);
    }

    /**
     * Inline keyboard for the Telebirr-style premium payment card.
     *
     * @return array{inline_keyboard: list<list<array<string, mixed>>>}
     */
    public function telebirrCardInlineKeyboard(): array
    {
        $premiumMiniAppUrl = app(TelegramService::class)->miniAppUrl('tg.premium');

        return $this->inlineKeyboard([
            [
                [
                    'text' => self::BUTTON_SEND_SCREENSHOT,
                    'callback_data' => 'pay:screenshot',
                ],
            ],
            [
                [
                    'text' => self::BUTTON_CBE,
                    'callback_data' => 'pay:cbe',
                ],
                [
                    'text' => self::BUTTON_TELEBIRR,
                    'callback_data' => 'pay:telebirr',
                    'style' => TelegramButtonStyle::Success->value,
                ],
            ],
            [
                [
                    'text' => self::BUTTON_ABOUT_PREMIUM,
                    'web_app' => ['url' => $premiumMiniAppUrl],
                    'style' => TelegramButtonStyle::Primary->value,
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function sendMessage(int|string $chatId, string $text, array $payload = []): ?array
    {
        return $this->api()->call('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function sendPhoto(int|string $chatId, string $photoFileId, string $caption = '', array $payload = []): ?array
    {
        return $this->api()->call('sendPhoto', array_merge([
            'chat_id' => $chatId,
            'photo' => $photoFileId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function sendDocument(int|string $chatId, string $documentFileId, string $caption = '', array $payload = []): ?array
    {
        return $this->api()->call('sendDocument', array_merge([
            'chat_id' => $chatId,
            'document' => $documentFileId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $payload));
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): ?array
    {
        $params = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $params['text'] = $text;
            $params['show_alert'] = $showAlert;
        }

        return $this->api()->call('answerCallbackQuery', $params);
    }

    /**
     * @param  array<string, mixed>  $replyMarkup
     */
    public function editMessageReplyMarkup(int|string $chatId, int $messageId, array $replyMarkup): ?array
    {
        return $this->api()->call('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $replyMarkup
     */
    public function editMessageCaption(
        int|string $chatId,
        int $messageId,
        string $caption,
        ?array $replyMarkup = null,
    ): ?array {
        return $this->api()->call('editMessageCaption', array_filter([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
            'reply_markup' => $replyMarkup ?? ['inline_keyboard' => []],
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function editMessageText(
        int|string $chatId,
        int $messageId,
        string $text,
        array $payload = [],
    ): ?array {
        return $this->api()->call('editMessageText', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $payload));
    }

    /**
     * @param  list<list<array<string, mixed>>>  $rows
     * @return array{inline_keyboard: list<list<array<string, mixed>>>}
     */
    public function inlineKeyboard(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }
}
