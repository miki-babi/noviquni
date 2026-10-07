<?php

namespace App\Services;

use App\Enums\BroadcastButtonType;
use App\Enums\BroadcastStatus;
use App\Enums\ReferralStatus;
use App\Enums\TelegramButtonStyle;
use App\Models\Broadcast;
use App\Models\NotificationDelivery;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BroadcastService
{
    public const string InlineTextReplyCallbackPrefix = 'reply:';

    public const string InlineChoiceCallbackPrefix = 'bcq:';

    public const int InlineTextReplyCallbackMaxBytes = 64;

    public const int InlineTextReplyPayloadMaxBytes = 58;

    public const int MaxChoiceButtons = 2;

    public function __construct(public TelegramService $telegram) {}

    /**
     * @return Builder<User>
     */
    public function audienceQuery(Broadcast $broadcast): Builder
    {
        $targeting = $broadcast->targeting ?? [];

        $query = User::query()
            ->students()
            ->active()
            ->where('notifications_enabled', true)
            ->whereNotNull('telegram_id');

        $audience = $targeting['audience'] ?? 'everyone';

        if ($audience === 'premium') {
            $query->whereNotNull('premium_until')->where('premium_until', '>', now());
        }

        if ($audience === 'free') {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('premium_until')
                    ->orWhere('premium_until', '<=', now());
            });
        }

        if (! empty($targeting['university_id'])) {
            $query->where('university_id', $targeting['university_id']);
        }

        if (! empty($targeting['stream_id'])) {
            $query->where('stream_id', $targeting['stream_id']);
        }

        if (! empty($targeting['course_id'])) {
            $query->whereHas('courses', fn (Builder $builder) => $builder->where('courses.id', $targeting['course_id']));
        }

        $minReferrals = $targeting['min_referrals'] ?? null;
        $maxReferrals = $targeting['max_referrals'] ?? null;

        if ($minReferrals !== null && $minReferrals !== '') {
            $query->whereRaw(
                '(select count(*) from referrals where referrals.referrer_id = users.id and referrals.status in (?, ?)) >= ?',
                [ReferralStatus::Qualified->value, ReferralStatus::Completed->value, (int) $minReferrals],
            );
        }

        if ($maxReferrals !== null && $maxReferrals !== '') {
            $query->whereRaw(
                '(select count(*) from referrals where referrals.referrer_id = users.id and referrals.status in (?, ?)) <= ?',
                [ReferralStatus::Qualified->value, ReferralStatus::Completed->value, (int) $maxReferrals],
            );
        }

        return $query;
    }

    public function personalize(string $body, User $user): string
    {
        $user->loadMissing(['university', 'stream', 'courses']);

        $firstName = explode(' ', trim($user->name))[0] ?: $user->name;
        $referralCount = Referral::query()
            ->where('referrer_id', $user->id)
            ->whereIn('status', [ReferralStatus::Qualified, ReferralStatus::Completed])
            ->count();

        return str_replace(
            ['{{first_name}}', '{{university}}', '{{stream}}', '{{course}}', '{{referral_count}}', '{{referral_points}}'],
            [
                $firstName,
                $user->university?->name ?? '',
                $user->stream?->name ?? '',
                $user->courses->first()?->name ?? '',
                (string) $referralCount,
                (string) $user->referral_points,
            ],
            $body,
        );
    }

    /**
     * Build Telegram InlineKeyboardMarkup from broadcast button rows.
     *
     * @param  array<int, array{label?: string, type?: string, command?: string, url?: string, response?: string, style?: string|null}>|null  $buttons
     * @return array{inline_keyboard: array<int, array<int, array<string, mixed>>>}|null
     */
    public function inlineKeyboard(?array $buttons, ?Broadcast $broadcast = null): ?array
    {
        if (blank($buttons)) {
            return null;
        }

        $choiceRow = [];
        $otherRows = [];
        $choiceIndex = 0;

        foreach ($buttons as $button) {
            $label = trim((string) ($button['label'] ?? ''));
            $type = BroadcastButtonType::tryFrom((string) ($button['type'] ?? ''));

            if ($label === '' || $type === null) {
                continue;
            }

            if ($type === BroadcastButtonType::Choice) {
                if ($broadcast === null || $choiceIndex >= self::MaxChoiceButtons) {
                    continue;
                }

                $response = trim((string) ($button['response'] ?? ''));

                if ($response === '') {
                    continue;
                }

                $telegramButton = [
                    'text' => $label,
                    'callback_data' => self::InlineChoiceCallbackPrefix.$broadcast->id.':'.$choiceIndex,
                ];

                $style = TelegramButtonStyle::tryFrom((string) ($button['style'] ?? ''));

                if ($style !== null) {
                    $telegramButton['style'] = $style->value;
                }

                $choiceRow[] = $telegramButton;
                $choiceIndex++;

                continue;
            }

            $telegramButton = match ($type) {
                BroadcastButtonType::Command => [
                    'text' => $label,
                    'callback_data' => mb_substr(trim((string) ($button['command'] ?? '')), 0, 64),
                ],
                BroadcastButtonType::Text => [
                    'text' => $label,
                    'callback_data' => self::InlineTextReplyCallbackPrefix.$this->inlineTextReplyPayload($button, $label),
                ],
                BroadcastButtonType::Url => [
                    'text' => $label,
                    'url' => trim((string) ($button['url'] ?? '')),
                ],
                BroadcastButtonType::MiniApp => [
                    'text' => $label,
                    'web_app' => [
                        'url' => trim((string) ($button['url'] ?? '')),
                    ],
                ],
                BroadcastButtonType::Choice => null,
            };

            if ($telegramButton === null) {
                continue;
            }

            if (
                ($type === BroadcastButtonType::Command && blank($telegramButton['callback_data']))
                || ($type === BroadcastButtonType::Text && blank(Str::after($telegramButton['callback_data'], self::InlineTextReplyCallbackPrefix)))
                || ($type === BroadcastButtonType::Url && blank($telegramButton['url']))
                || ($type === BroadcastButtonType::MiniApp && blank($telegramButton['web_app']['url']))
            ) {
                continue;
            }

            if ($type === BroadcastButtonType::Text) {
                $telegramButton['callback_data'] = mb_substr(
                    $telegramButton['callback_data'],
                    0,
                    self::InlineTextReplyCallbackMaxBytes,
                );
            }

            $style = TelegramButtonStyle::tryFrom((string) ($button['style'] ?? ''));

            if ($style !== null) {
                $telegramButton['style'] = $style->value;
            }

            $otherRows[] = [$telegramButton];
        }

        $rows = [];

        if ($choiceRow !== []) {
            $rows[] = $choiceRow;
        }

        array_push($rows, ...$otherRows);

        if ($rows === []) {
            return null;
        }

        return ['inline_keyboard' => $rows];
    }

    /**
     * @return array{broadcast_id: int, choice_index: int}|null
     */
    public function parseChoiceCallback(string $data): ?array
    {
        if (! str_starts_with($data, self::InlineChoiceCallbackPrefix)) {
            return null;
        }

        $payload = Str::after($data, self::InlineChoiceCallbackPrefix);
        $parts = explode(':', $payload, 2);

        if (count($parts) !== 2 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1])) {
            return null;
        }

        return [
            'broadcast_id' => (int) $parts[0],
            'choice_index' => (int) $parts[1],
        ];
    }

    public function choiceResponse(Broadcast $broadcast, int $choiceIndex): ?string
    {
        if ($choiceIndex < 0 || $choiceIndex >= self::MaxChoiceButtons) {
            return null;
        }

        $choices = collect($broadcast->buttons ?? [])
            ->filter(fn (mixed $button): bool => is_array($button)
                && ($button['type'] ?? null) === BroadcastButtonType::Choice->value
                && filled(trim((string) ($button['response'] ?? ''))))
            ->values();

        $button = $choices->get($choiceIndex);

        if (! is_array($button)) {
            return null;
        }

        $response = trim((string) ($button['response'] ?? ''));

        return $response !== '' ? $response : null;
    }

    /**
     * @param  array{label?: string, command?: string}  $button
     */
    protected function inlineTextReplyPayload(array $button, string $label): string
    {
        $reply = trim((string) ($button['command'] ?? ''));

        if ($reply === '') {
            $reply = $label;
        }

        return mb_substr($reply, 0, self::InlineTextReplyPayloadMaxBytes);
    }

    /**
     * @param  array<int, array{label?: string, type?: string, command?: string, url?: string, response?: string, style?: string|null}>|null  $buttons
     */
    public function sendToUser(User $user, string $body, ?array $buttons = null): bool
    {
        if (blank($user->telegram_id) || ! $this->telegram->isConfigured()) {
            return false;
        }

        $body = $this->personalize($body, $user);

        $payload = [];
        $replyMarkup = $this->inlineKeyboard($buttons);

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $result = $this->telegram->sendMessage($user->telegram_id, $body, $payload);
        $delivered = $result !== null;
        $messageId = $delivered && isset($result['message_id'])
            ? (int) $result['message_id']
            : null;

        NotificationDelivery::query()->create([
            'broadcast_id' => null,
            'user_id' => $user->id,
            'channel' => 'telegram',
            'status' => $delivered ? 'sent' : 'failed',
            'body' => $body,
            'sent_at' => $delivered ? now() : null,
            'telegram_message_id' => $messageId,
        ]);

        return $delivered;
    }

    public function send(Broadcast $broadcast): void
    {
        $broadcast->update(['status' => BroadcastStatus::Sending, 'scheduled_at' => null]);

        $replyMarkup = $this->inlineKeyboard($broadcast->buttons, $broadcast);

        $this->audienceQuery($broadcast)
            ->orderBy('id')
            ->chunkById(100, function (Collection $users) use ($broadcast, $replyMarkup): void {
                foreach ($users as $user) {
                    $body = $this->personalize($broadcast->body, $user);

                    $payload = [];

                    if ($replyMarkup !== null) {
                        $payload['reply_markup'] = $replyMarkup;
                    }

                    $result = $this->telegram->sendMessage($user->telegram_id, $body, $payload);
                    $delivered = $result !== null;
                    $messageId = $delivered && isset($result['message_id'])
                        ? (int) $result['message_id']
                        : null;

                    NotificationDelivery::query()->create([
                        'broadcast_id' => $broadcast->id,
                        'user_id' => $user->id,
                        'channel' => 'telegram',
                        'status' => $delivered ? 'sent' : 'failed',
                        'body' => $body,
                        'sent_at' => $delivered ? now() : null,
                        'telegram_message_id' => $messageId,
                    ]);
                }
            });

        $broadcast->update([
            'status' => BroadcastStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    /**
     * @return array{deleted: int, failed: int, skipped: int}
     */
    public function deleteFromTelegram(Broadcast $broadcast): array
    {
        $counts = [
            'deleted' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $broadcast->deliveries()
            ->with('user')
            ->where('status', 'sent')
            ->orderBy('id')
            ->chunkById(100, function (Collection $deliveries) use (&$counts): void {
                foreach ($deliveries as $delivery) {
                    if ($delivery->telegram_message_id === null || blank($delivery->user?->telegram_id)) {
                        $counts['skipped']++;

                        continue;
                    }

                    $result = $this->telegram->deleteMessage(
                        $delivery->user->telegram_id,
                        (int) $delivery->telegram_message_id,
                    );

                    if ($result === null) {
                        $counts['failed']++;

                        continue;
                    }

                    $delivery->update(['status' => 'deleted']);
                    $counts['deleted']++;
                }
            });

        return $counts;
    }
}
