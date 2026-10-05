<?php

namespace App\Services;

use App\Jobs\SendTelegramMessageJob;
use App\Models\User;
use App\Support\TelegramHtml;

class NewStudentAdminNotifier
{
    public function notify(User $student): void
    {
        $recipientTelegramIds = collect($this->fileAdminUsernames())
            ->map(function (string $username): ?string {
                return User::query()
                    ->whereNotNull('telegram_id')
                    ->whereRaw('LOWER(telegram_username) = ?', [$username])
                    ->value('telegram_id');
            })
            ->filter()
            ->unique()
            ->values();

        if ($recipientTelegramIds->isEmpty()) {
            return;
        }

        $totalStudents = User::query()->students()->count();

        $usernameLine = filled($student->telegram_username)
            ? '@'.TelegramHtml::escape(ltrim($student->telegram_username, '@'))
            : TelegramHtml::escape('—');

        $text = '<b>New student joined</b>'
            ."\nName: ".TelegramHtml::escape($student->name)
            ."\nUsername: ".$usernameLine
            ."\nTotal students: ".number_format($totalStudents);

        foreach ($recipientTelegramIds as $telegramId) {
            SendTelegramMessageJob::dispatch($telegramId, $text);
        }
    }

    /**
     * @return list<string>
     */
    protected function fileAdminUsernames(): array
    {
        $raw = (string) config('services.telegram.file_admin_username', '');

        return collect(explode(',', $raw))
            ->map(fn (string $username): string => strtolower(ltrim(trim($username), '@')))
            ->filter()
            ->values()
            ->all();
    }
}
