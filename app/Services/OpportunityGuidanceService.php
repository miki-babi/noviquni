<?php

namespace App\Services;

use App\Enums\GuidanceRequestStatus;
use App\Enums\TelegramButtonStyle;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Opportunity;
use App\Models\OpportunityGuidanceRequest;
use App\Models\User;
use App\Support\TelegramCopy;
use App\Support\TelegramHtml;
use InvalidArgumentException;

class OpportunityGuidanceService
{
    public function __construct(
        public SettingsService $settings,
        public PremiumService $premium,
        public ReferralService $referrals,
        public TelegramService $telegram,
    ) {}

    public function hasRequested(User $student, Opportunity $opportunity): bool
    {
        return OpportunityGuidanceRequest::query()
            ->where('user_id', $student->id)
            ->where('opportunity_id', $opportunity->id)
            ->exists();
    }

    /**
     * Send the premium pitch with a Become Premium inline button (payment bot URL when configured).
     */
    public function sendPremiumRequiredPitch(User $user): void
    {
        if (blank($user->telegram_id)) {
            return;
        }

        $copy = TelegramCopy::for($user);
        $title = $this->settings->premiumPitchTitle();
        $body = $this->settings->premiumPitchBody();
        $required = $this->settings->requiredReferrals();
        $progress = $this->referrals->qualifiedCount($user);
        $referralLine = $copy->get('opportunities.guidance_referral_progress', [
            'progress' => $progress,
            'required' => $required,
        ]);
        $price = $this->settings->premiumPrice();

        $becomePremium = [
            'text' => $copy->get('opportunities.guidance_unlock_premium'),
            'style' => TelegramButtonStyle::Primary->value,
        ];

        $paymentBotUrl = $this->settings->paymentBotUrl($user);

        if ($paymentBotUrl !== null) {
            $becomePremium['url'] = $paymentBotUrl;
        } else {
            $becomePremium['callback_data'] = 'premium_pay';
        }

        $this->telegram->sendMessage(
            $user->telegram_id,
            $copy->get('opportunities.guidance_premium_required')."\n\n<b>".TelegramHtml::escape($title).'</b>'."\n"
            .TelegramHtml::escape($body)
            ."\n\nPrice: {$price} ETB\n"
            .TelegramHtml::escape($referralLine),
            [
                'reply_markup' => $this->telegram->inlineKeyboard([
                    [$becomePremium],
                    [[
                        'text' => $copy->get('menu.refer'),
                        'web_app' => ['url' => $this->telegram->miniAppUrl('tg.profile')],
                    ]],
                ]),
            ],
        );
    }

    /**
     * @return array{notified: bool, request: OpportunityGuidanceRequest}
     */
    public function requestGuidance(User $student, Opportunity $opportunity): array
    {
        if (! $opportunity->hasGuidanceAvailable()) {
            throw new InvalidArgumentException('Opportunity does not offer guidance.');
        }

        if (! $this->premium->canRequestOpportunityGuidance($student)) {
            throw new InvalidArgumentException('Opportunity guidance requires an active premium subscription.');
        }

        $existing = OpportunityGuidanceRequest::query()
            ->where('user_id', $student->id)
            ->where('opportunity_id', $opportunity->id)
            ->first();

        if ($existing !== null) {
            return [
                'notified' => false,
                'request' => $existing,
            ];
        }

        $request = OpportunityGuidanceRequest::query()->create([
            'user_id' => $student->id,
            'opportunity_id' => $opportunity->id,
            'status' => GuidanceRequestStatus::Pending,
        ]);

        return [
            'notified' => $this->notifyAdmins($student, $opportunity),
            'request' => $request,
        ];
    }

    public function assign(OpportunityGuidanceRequest $request, User $assignee): OpportunityGuidanceRequest
    {
        $username = ltrim(trim((string) $assignee->telegram_username), '@');

        if ($username === '') {
            throw new InvalidArgumentException('Assignee must have a Telegram username.');
        }

        $request->loadMissing(['user', 'opportunity']);

        $student = $request->user;
        $opportunity = $request->opportunity;

        if ($student === null || $opportunity === null) {
            throw new InvalidArgumentException('Guidance request is missing student or opportunity.');
        }

        $request->update([
            'status' => GuidanceRequestStatus::Assigned,
            'assigned_to_user_id' => $assignee->id,
            'assigned_at' => now(),
        ]);

        $this->notifyStudentAssigned($student, $opportunity, $username);

        return $request->fresh(['user', 'opportunity', 'assignee']) ?? $request;
    }

    public function notifyAdmins(User $student, Opportunity $opportunity): bool
    {
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

    public function assigneeDeepLink(User $student, Opportunity $opportunity, string $assigneeUsername): string
    {
        return 'https://t.me/'.ltrim($assigneeUsername, '@').'?text='.rawurlencode(
            $this->prefilledStudentMessage($student, $opportunity)
        );
    }

    public function prefilledStudentMessage(User $student, Opportunity $opportunity): string
    {
        $template = filled($opportunity->guidance_opening_message)
            ? (string) $opportunity->guidance_opening_message
            : $this->settings->opportunityGuidanceOpeningMessage();

        $studentLabel = filled($student->telegram_username)
            ? '@'.ltrim((string) $student->telegram_username, '@')
            : $student->name;

        return strtr($template, [
            '{title}' => $opportunity->title,
            '{partner}' => $this->partnerLabel($opportunity),
            '{student}' => $studentLabel,
        ]);
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
            ."\nPartner: ".TelegramHtml::escape($this->partnerLabel($opportunity));
    }

    protected function partnerLabel(Opportunity $opportunity): string
    {
        return filled($opportunity->partner_name)
            ? (string) $opportunity->partner_name
            : $opportunity->title;
    }

    protected function notifyStudentAssigned(
        User $student,
        Opportunity $opportunity,
        string $assigneeUsername,
    ): void {
        if (blank($student->telegram_id)) {
            return;
        }

        $copy = TelegramCopy::for($student);
        $assigneeLabel = '@'.$assigneeUsername;
        $text = $copy->get('opportunities.guidance_assigned', [
            'title' => $opportunity->title,
            'assignee' => $assigneeLabel,
        ]);
        $deepLink = $this->assigneeDeepLink($student, $opportunity, $assigneeUsername);

        SendTelegramMessageJob::dispatch($student->telegram_id, $text, [
            'reply_markup' => [
                'inline_keyboard' => [[
                    [
                        'text' => $copy->get('opportunities.guidance_open_chat'),
                        'url' => $deepLink,
                        'style' => TelegramButtonStyle::Primary->value,
                    ],
                ]],
            ],
        ]);
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
}
