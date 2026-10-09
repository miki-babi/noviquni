<?php

namespace App\Services;

use App\Models\User;

class FileAdminRecipients
{
    /**
     * @return list<string>
     */
    public function usernames(): array
    {
        $raw = (string) config('services.telegram.file_admin_username', '');

        return collect(explode(',', $raw))
            ->map(fn (string $username): string => strtolower(ltrim(trim($username), '@')))
            ->filter()
            ->values()
            ->all();
    }

    public function isAdmin(?string $username): bool
    {
        if (blank($username)) {
            return false;
        }

        $normalized = strtolower(ltrim(trim($username), '@'));

        return in_array($normalized, $this->usernames(), true);
    }

    /**
     * @return list<string>
     */
    public function telegramIds(): array
    {
        return collect($this->usernames())
            ->map(function (string $username): ?string {
                $telegramId = User::query()
                    ->whereNotNull('telegram_id')
                    ->whereRaw('LOWER(telegram_username) = ?', [$username])
                    ->value('telegram_id');

                return $telegramId !== null ? (string) $telegramId : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
