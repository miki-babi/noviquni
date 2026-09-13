<?php

namespace App\Services;

use App\Enums\BroadcastButtonType;
use App\Enums\BroadcastStatus;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Broadcast;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BroadcastService
{
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

        return $query;
    }

    public function personalize(string $body, User $user): string
    {
        $user->loadMissing(['university', 'stream', 'courses']);

        $firstName = explode(' ', trim($user->name))[0] ?: $user->name;

        return str_replace(
            ['{{first_name}}', '{{university}}', '{{stream}}', '{{course}}'],
            [
                $firstName,
                $user->university?->name ?? '',
                $user->stream?->name ?? '',
                $user->courses->first()?->name ?? '',
            ],
            $body,
        );
    }

    /**
     * Build Telegram InlineKeyboardMarkup from broadcast button rows.
     *
     * @param  array<int, array{label?: string, type?: string, command?: string, url?: string}>|null  $buttons
     * @return array{inline_keyboard: array<int, array<int, array<string, mixed>>>}|null
     */
    public function inlineKeyboard(?array $buttons): ?array
    {
        if (blank($buttons)) {
            return null;
        }

        $rows = [];

        foreach ($buttons as $button) {
            $label = trim((string) ($button['label'] ?? ''));
            $type = BroadcastButtonType::tryFrom((string) ($button['type'] ?? ''));

            if ($label === '' || $type === null) {
                continue;
            }

            $telegramButton = match ($type) {
                BroadcastButtonType::Command => [
                    'text' => $label,
                    'callback_data' => mb_substr(trim((string) ($button['command'] ?? '')), 0, 64),
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
            };

            if (
                ($type === BroadcastButtonType::Command && blank($telegramButton['callback_data']))
                || ($type === BroadcastButtonType::Url && blank($telegramButton['url']))
                || ($type === BroadcastButtonType::MiniApp && blank($telegramButton['web_app']['url']))
            ) {
                continue;
            }

            $rows[] = [$telegramButton];
        }

        if ($rows === []) {
            return null;
        }

        return ['inline_keyboard' => $rows];
    }

    public function send(Broadcast $broadcast): void
    {
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        $replyMarkup = $this->inlineKeyboard($broadcast->buttons);

        $this->audienceQuery($broadcast)
            ->orderBy('id')
            ->chunkById(100, function (Collection $users) use ($broadcast, $replyMarkup): void {
                foreach ($users as $user) {
                    $body = $this->personalize($broadcast->body, $user);

                    NotificationDelivery::query()->create([
                        'broadcast_id' => $broadcast->id,
                        'user_id' => $user->id,
                        'channel' => 'telegram',
                        'status' => 'queued',
                        'body' => $body,
                    ]);

                    $payload = [];

                    if ($replyMarkup !== null) {
                        $payload['reply_markup'] = $replyMarkup;
                    }

                    SendTelegramMessageJob::dispatch($user->telegram_id, $body, $payload);
                }
            });

        NotificationDelivery::query()
            ->where('broadcast_id', $broadcast->id)
            ->where('status', 'queued')
            ->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

        $broadcast->update([
            'status' => BroadcastStatus::Sent,
            'sent_at' => now(),
        ]);
    }
}
