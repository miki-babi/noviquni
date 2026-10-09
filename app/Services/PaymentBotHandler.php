<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Support\TelegramHtml;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentBotHandler
{
    public function __construct(
        public PaymentBotTelegramService $telegram,
        public PaymentService $payments,
        public SettingsService $settings,
        public FileAdminRecipients $fileAdmins,
    ) {}

    /**
     * @param  array<string, mixed>  $update
     */
    public function handle(array $update): void
    {
        if (! $this->telegram->isConfigured()) {
            Log::warning('payment_bot.token_missing');

            return;
        }

        if (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);

            return;
        }

        if (isset($update['message']) && is_array($update['message'])) {
            $this->handleMessage($update['message']);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];

        if ($chatId === null) {
            return;
        }

        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));

        if (str_starts_with($text, '/start')) {
            $payload = trim(Str::after($text, '/start'));
            $this->handleStart($chatId, $from, $payload !== '' ? $payload : null);

            return;
        }

        $receipt = $this->extractReceiptFile($message);

        if ($receipt !== null) {
            $this->handleReceiptPhoto($chatId, $from, $receipt['file_id'], $receipt['kind']);

            return;
        }

        $student = $this->resolveStudent($from, null);

        if ($student === null) {
            $this->telegram->sendMessage(
                $chatId,
                'Open <b>Premium</b> in the main NoviqUni bot first, then tap Become Premium to continue payment here.',
            );

            return;
        }

        if ($student->hasActivePremium()) {
            $this->telegram->sendMessage($chatId, 'Premium is already active. You are all set.');

            return;
        }

        $this->sendPaymentBriefing($chatId, $student);
    }

    /**
     * @param  array<string, mixed>  $from
     */
    protected function handleStart(int|string $chatId, array $from, ?string $payload): void
    {
        $student = $this->resolveStudent($from, $payload);

        if ($student === null) {
            $this->telegram->sendMessage(
                $chatId,
                'We could not match your account. Open <b>Premium</b> in the main NoviqUni bot and tap Become Premium so we can link you here.',
            );

            return;
        }

        $this->syncTelegramProfile($student, $from);

        if ($student->hasActivePremium()) {
            $this->telegram->sendMessage($chatId, 'Premium is already active. You are all set.');

            return;
        }

        $this->sendPaymentBriefing($chatId, $student);
    }

    protected function sendPaymentBriefing(int|string $chatId, User $student): void
    {
        $payment = $this->payments->createOrReusePendingPaymentBotPayment($student);
        $title = $this->settings->premiumPitchTitle();
        $body = $this->settings->premiumPitchBody();
        $instructions = $this->payments->instructionsFor($payment);

        $text = '<b>'.TelegramHtml::escape($title)."</b>\n"
            .TelegramHtml::escape($body)
            ."\n\n<b>Price:</b> {$payment->amount} {$payment->currency}"
            ."\n<b>Reference:</b> ".TelegramHtml::escape((string) $payment->external_ref)
            ."\n\n".TelegramHtml::escape($instructions)
            ."\n\nWhen you have paid, send a <b>photo</b> of the transfer receipt here.";

        $this->telegram->sendMessage($chatId, $text);
    }

    /**
     * @param  array<string, mixed>  $from
     */
    protected function handleReceiptPhoto(int|string $chatId, array $from, string $fileId, string $kind): void
    {
        $student = $this->resolveStudent($from, null);

        if ($student === null) {
            $this->telegram->sendMessage(
                $chatId,
                'We could not match your account. Open Premium in the main bot first, then come back.',
            );

            return;
        }

        $this->syncTelegramProfile($student, $from);

        if ($student->hasActivePremium()) {
            $this->telegram->sendMessage($chatId, 'Premium is already active. No receipt needed.');

            return;
        }

        $payment = $this->payments->createOrReusePendingPaymentBotPayment($student);
        $meta = $payment->meta ?? [];
        $meta['receipt_file_id'] = $fileId;
        $meta['receipt_kind'] = $kind;
        $payment->update(['meta' => $meta]);

        $adminIds = $this->fileAdmins->telegramIds();

        if ($adminIds === []) {
            $this->telegram->sendMessage(
                $chatId,
                'Thanks — we received your screenshot, but no reviewer is available right now. Please try again later or contact support.',
            );

            return;
        }

        $usernameLine = filled($student->telegram_username)
            ? '@'.TelegramHtml::escape(ltrim((string) $student->telegram_username, '@'))
            : '—';

        $caption = '<b>Premium payment screenshot</b>'
            ."\nName: ".TelegramHtml::escape($student->name)
            ."\nUsername: ".$usernameLine
            ."\nUser ID: {$student->id}"
            ."\nAmount: {$payment->amount} {$payment->currency}"
            ."\nReference: ".TelegramHtml::escape((string) $payment->external_ref)
            ."\nPayment ID: {$payment->id}";

        $keyboard = $this->telegram->inlineKeyboard([[
            ['text' => 'Approve', 'callback_data' => 'pay:v:'.$payment->id],
            ['text' => 'Reject', 'callback_data' => 'pay:r:'.$payment->id],
        ]]);

        foreach ($adminIds as $adminChatId) {
            if ($kind === 'document') {
                $this->telegram->sendDocument($adminChatId, $fileId, $caption, [
                    'reply_markup' => $keyboard,
                ]);
            } else {
                $this->telegram->sendPhoto($adminChatId, $fileId, $caption, [
                    'reply_markup' => $keyboard,
                ]);
            }
        }

        $this->telegram->sendMessage(
            $chatId,
            'Screenshot received. We will review it shortly and unlock Premium when approved.',
        );
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    protected function handleCallback(array $callback): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $data = (string) ($callback['data'] ?? '');
        $from = is_array($callback['from'] ?? null) ? $callback['from'] : [];
        $message = is_array($callback['message'] ?? null) ? $callback['message'] : [];
        $chatId = $message['chat']['id'] ?? null;
        $messageId = isset($message['message_id']) ? (int) $message['message_id'] : null;

        if ($callbackId === '' || $chatId === null) {
            return;
        }

        if (! $this->fileAdmins->isAdmin($from['username'] ?? null)) {
            $this->telegram->answerCallbackQuery($callbackId, 'Not authorized.', true);

            return;
        }

        if (! preg_match('/^pay:(v|r|u):(\d+)$/', $data, $matches)) {
            $this->telegram->answerCallbackQuery($callbackId);

            return;
        }

        $action = $matches[1];
        $payment = Payment::query()->find((int) $matches[2]);

        if ($payment === null || $payment->provider !== Payment::PROVIDER_PAYMENT_BOT) {
            $this->telegram->answerCallbackQuery($callbackId, 'Payment not found.', true);

            return;
        }

        $payment->loadMissing('user');
        $adminLabel = filled($from['username'] ?? null)
            ? '@'.ltrim((string) $from['username'], '@')
            : 'admin';

        if ($action === 'u') {
            if ($payment->status !== PaymentStatus::Verified) {
                $this->telegram->answerCallbackQuery($callbackId, 'Nothing to revert.');

                return;
            }

            $this->payments->revertViaPaymentBot($payment->fresh() ?? $payment);
            $payment = $payment->fresh() ?? $payment;
            $payment->loadMissing('user');

            $this->telegram->answerCallbackQuery($callbackId, 'Reverted.');
            if ($messageId !== null) {
                $this->finalizeAdminMessage($chatId, $messageId, $message, 'Reverted by '.$adminLabel);
            }

            if ($payment->user?->telegram_id) {
                $this->telegram->sendMessage(
                    $payment->user->telegram_id,
                    'Your payment approval was reverted. Premium from this payment is no longer active. Contact support if this is unexpected (ref '
                    .TelegramHtml::escape((string) $payment->external_ref).').',
                );
            }

            return;
        }

        if ($payment->status !== PaymentStatus::Pending) {
            $this->telegram->answerCallbackQuery($callbackId, 'Already handled.');
            if ($messageId !== null) {
                $this->finalizeAdminMessage($chatId, $messageId, $message, 'Already '.$payment->status->value);
            }

            return;
        }

        if ($action === 'v') {
            $this->payments->verifyViaPaymentBot($payment);
            $this->telegram->answerCallbackQuery($callbackId, 'Approved.');
            if ($messageId !== null) {
                $this->finalizeAdminMessage(
                    $chatId,
                    $messageId,
                    $message,
                    'Approved by '.$adminLabel,
                    undoPaymentId: $payment->id,
                );
            }

            if ($payment->user?->telegram_id) {
                $this->telegram->sendMessage(
                    $payment->user->telegram_id,
                    'Payment approved. Premium is now active — thank you!',
                );
            }

            return;
        }

        $this->payments->rejectViaPaymentBot($payment);
        $this->telegram->answerCallbackQuery($callbackId, 'Rejected.');
        if ($messageId !== null) {
            $this->finalizeAdminMessage($chatId, $messageId, $message, 'Rejected by '.$adminLabel);
        }

        if ($payment->user?->telegram_id) {
            $this->telegram->sendMessage(
                $payment->user->telegram_id,
                'Your payment screenshot was not approved. Send a clearer receipt or contact support with reference '
                .TelegramHtml::escape((string) $payment->external_ref).'.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function finalizeAdminMessage(
        int|string $chatId,
        int $messageId,
        array $message,
        string $statusLine,
        ?int $undoPaymentId = null,
    ): void {
        $caption = trim((string) ($message['caption'] ?? ''));
        $updated = ($caption !== '' ? $caption."\n\n" : '').'<b>'.TelegramHtml::escape($statusLine).'</b>';

        $replyMarkup = $undoPaymentId !== null
            ? $this->telegram->inlineKeyboard([[
                ['text' => 'Undo approve', 'callback_data' => 'pay:u:'.$undoPaymentId],
            ]])
            : ['inline_keyboard' => []];

        $this->telegram->editMessageCaption($chatId, $messageId, $updated, $replyMarkup);
    }

    /**
     * @param  array<string, mixed>  $from
     */
    protected function resolveStudent(array $from, ?string $startPayload): ?User
    {
        if (filled($startPayload) && preg_match('/^u(\d+)$/i', trim($startPayload), $matches)) {
            $user = User::query()->find((int) $matches[1]);

            if ($user !== null) {
                return $user;
            }
        }

        $telegramId = $from['id'] ?? null;

        if ($telegramId === null) {
            return null;
        }

        return User::query()->where('telegram_id', (string) $telegramId)->first();
    }

    /**
     * @param  array<string, mixed>  $from
     */
    protected function syncTelegramProfile(User $student, array $from): void
    {
        $telegramId = isset($from['id']) ? (string) $from['id'] : null;
        $username = isset($from['username']) ? (string) $from['username'] : null;
        $name = trim(implode(' ', array_filter([
            $from['first_name'] ?? null,
            $from['last_name'] ?? null,
        ])));

        $updates = [];

        if ($telegramId !== null && $student->telegram_id !== $telegramId) {
            $updates['telegram_id'] = $telegramId;
        }

        if ($username !== null && $student->telegram_username !== $username) {
            $updates['telegram_username'] = $username;
        }

        if ($name !== '' && $student->name !== $name) {
            $updates['name'] = $name;
        }

        if ($updates !== []) {
            $student->update($updates);
            $student->refresh();
        }
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{file_id: string, kind: string}|null
     */
    protected function extractReceiptFile(array $message): ?array
    {
        $photos = $message['photo'] ?? null;

        if (is_array($photos) && $photos !== []) {
            $largest = $photos[array_key_last($photos)];

            if (is_array($largest) && filled($largest['file_id'] ?? null)) {
                return [
                    'file_id' => (string) $largest['file_id'],
                    'kind' => 'photo',
                ];
            }
        }

        $document = $message['document'] ?? null;

        if (is_array($document) && filled($document['file_id'] ?? null)) {
            $mime = (string) ($document['mime_type'] ?? '');

            if ($mime === '' || str_starts_with($mime, 'image/')) {
                return [
                    'file_id' => (string) $document['file_id'],
                    'kind' => 'document',
                ];
            }
        }

        return null;
    }
}
