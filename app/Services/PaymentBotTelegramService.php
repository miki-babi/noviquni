<?php

namespace App\Services;

class PaymentBotTelegramService
{
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

    public function editMessageCaption(int|string $chatId, int $messageId, string $caption): ?array
    {
        return $this->api()->call('editMessageCaption', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
            'reply_markup' => ['inline_keyboard' => []],
        ]);
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
