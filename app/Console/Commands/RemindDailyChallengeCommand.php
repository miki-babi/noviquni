<?php

namespace App\Console\Commands;

use App\Enums\TelegramButtonStyle;
use App\Jobs\SendTelegramMessageJob;
use App\Models\User;
use App\Services\DailyChallengeService;
use App\Services\TelegramService;
use App\Support\TelegramCopy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('daily:challenge-remind')]
#[Description('Remind students who have not completed today\'s daily challenge')]
class RemindDailyChallengeCommand extends Command
{
    public function handle(DailyChallengeService $dailyChallenges, TelegramService $telegram): int
    {
        $challenge = $dailyChallenges->today();

        if ($challenge === null) {
            $this->warn('No daily challenge for today; skipping reminders.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        $dailyChallenges->reminderAudienceQuery()
            ->with('streak')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($telegram, &$dispatched): void {
                foreach ($users as $user) {
                    /** @var User $user */
                    if ($user->telegram_id === null) {
                        continue;
                    }

                    $copy = TelegramCopy::for($user);
                    $streak = (int) ($user->streak?->current_streak ?? 0);

                    $text = $streak > 0
                        ? $copy->get('daily_challenge.remind_streak', ['streak' => $streak])
                        : $copy->get('daily_challenge.remind');

                    SendTelegramMessageJob::dispatch(
                        $user->telegram_id,
                        $text,
                        [
                            'reply_markup' => $telegram->inlineKeyboard([[
                                [
                                    'text' => $copy->get('daily_challenge.open_button'),
                                    'web_app' => ['url' => $telegram->miniAppUrl('tg.play.daily-challenge')],
                                    'style' => TelegramButtonStyle::Success->value,
                                ],
                            ]]),
                        ],
                    );

                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} daily challenge reminder(s).");

        return self::SUCCESS;
    }
}
