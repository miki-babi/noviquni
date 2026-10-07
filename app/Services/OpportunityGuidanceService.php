<?php

namespace App\Services;

use App\Jobs\SendTelegramMessageJob;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\TelegramHtml;
use Illuminate\Support\Facades\Cache;

class OpportunityGuidanceService
{
    /**
     * @return array{notified: bool, support_url: ?string}
     */
    public function requestGuidance(User $student, Opportunity $opportunity): array
    {
        return [
            'notified' => $this->notifyAdmins($student, $opportunity),
            'support_url' => $this->supportDeepLink($student, $opportunity),
        ];
    }

    public function notifyAdmins(User $student, Opportunity $opportunity): bool
    {
        if (! $opportunity->hasVerifiedPartner()) {
            return false;
        }

        $cacheKey = $this->cacheKey($student, $opportunity);

        if (! Cache::add($cacheKey, true, now()->addDay())) {
            return false;
        }

        $recipientTelegramIds = $this->adminTelegramIds();

        if ($recipientTelegramIds === []) {
            return true;
        }

        $text = $this->adminNotificationText($student, $opportunity);

        foreach ($recipientTelegramIds as $telegramId) {
            SendTelegramMessageJob::dispatch($telegramId, $text);
        }

        return true;
    }

    public function supportDeepLink(User $student, Opportunity $opportunity): ?string
    {
        $username = $this->supportUsername();

        if ($username === null) {
            return null;
        }

        return 'https://t.me/'.$username.'?text='.rawurlencode(
            $this->prefilledStudentMessage($student, $opportunity)
        );
    }

    public function prefilledStudentMessage(User $student, Opportunity $opportunity): string
    {
        $username = filled($student->telegram_username)
            ? '@'.ltrim((string) $student->telegram_username, '@')
            : $student->name;

        return "Hi, I'd like guidance on {$opportunity->title} (partner: {$opportunity->partner_name}).\n\nFrom: {$username}";
    }

    public function adminNotificationText(User $student, Opportunity $opportunity): string
    {
        $usernameLine = filled($student->telegram_username)
            ? '@'.TelegramHtml::escape(ltrim($student->telegram_username, '@'))
            : TelegramHtml::escape('—');

        return '<b>Opportunity guidance request</b>'
            ."\nStudent: ".TelegramHtml::escape($student->name)
            ."\nUsername: ".$usernameLine
            ."\nOpportunity: ".TelegramHtml::escape($opportunity->title)
            ."\nPartner: ".TelegramHtml::escape((string) $opportunity->partner_name);
    }

    public function supportUsername(): ?string
    {
        $raw = (string) config('services.telegram.file_admin_username', '');

        $username = collect(explode(',', $raw))
            ->map(fn (string $value): string => ltrim(trim($value), '@'))
            ->filter()
            ->first();

        return filled($username) ? $username : null;
    }

    /**
     * @return list<int|string>
     */
    protected function adminTelegramIds(): array
    {
        return collect($this->fileAdminUsernames())
            ->map(function (string $username): ?string {
                return User::query()
                    ->whereNotNull('telegram_id')
                    ->whereRaw('LOWER(telegram_username) = ?', [$username])
                    ->value('telegram_id');
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
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

    protected function cacheKey(User $student, Opportunity $opportunity): string
    {
        return "opportunity_guidance:{$student->id}:{$opportunity->id}";
    }
}
