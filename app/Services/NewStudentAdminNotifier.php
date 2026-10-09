<?php

namespace App\Services;

use App\Models\User;
use App\Support\TelegramHtml;

class NewStudentAdminNotifier
{
    public function __construct(
        public FileAdminRecipients $fileAdmins,
    ) {}

    public function notify(User $student, TelegramService $telegram): void
    {
        $recipientTelegramIds = $this->fileAdmins->telegramIds();

        if ($recipientTelegramIds === []) {
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
            $telegram->sendMessage($telegramId, $text);
        }
    }
}
