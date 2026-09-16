<?php

namespace App\Services;

use App\Enums\OnboardingStep;
use App\Enums\ResourceHub;
use App\Enums\ResourceType;
use App\Enums\RewardStatus;
use App\Enums\TelegramButtonStyle;
use App\Enums\TelegramLocale;
use App\Enums\WithdrawalStatus;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\ReferralReward;
use App\Models\ResourceDownload;
use App\Models\Semester;
use App\Models\Stream;
use App\Models\University;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\College\TelegramResourceFormatter;
use App\Support\TelegramCopy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TelegramBotHandler
{
    public function __construct(
        public TelegramService $telegram,
        public OnboardingService $onboarding,
        public ReferralService $referrals,
        public PaymentService $payments,
        public PremiumService $premium,
        public SettingsService $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $update
     */
    public function handle(array $update): void
    {
        if (! $this->shouldProcessUpdate($update)) {
            return;
        }

        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);

            return;
        }

        $message = $update['message'] ?? null;

        if ($message === null) {
            return;
        }

        $chatId = $message['chat']['id'];
        $text = trim((string) ($message['text'] ?? ''));
        $from = $message['from'] ?? [];
        $name = trim(($from['first_name'] ?? 'Student').' '.($from['last_name'] ?? ''));

        $user = $this->telegram->findOrCreateStudent(
            $from['id'],
            $name,
            $from['username'] ?? null,
        );

        $copy = TelegramCopy::for($user);

        if (! $user->is_active) {
            $this->telegram->sendMessage($chatId, $copy->get('menu.account_disabled'));

            return;
        }

        if (str_starts_with($text, '/start')) {
            $parts = explode(' ', $text, 2);
            $code = $parts[1] ?? null;

            if (filled($code) && $user->referred_by_user_id === null) {
                $this->referrals->attributeReferral($user, $code);
                $user->refresh();
            }

            if ($user->onboarding_step !== OnboardingStep::Complete) {
                $user->update(['onboarding_step' => OnboardingStep::Stream]);
                $this->askStream($user, $chatId);

                return;
            }

            $this->sendHome($user, $chatId);

            return;
        }

        if ($user->onboarding_step !== OnboardingStep::Complete && $user->onboarding_step !== null) {
            $this->repromptOnboarding($user, $chatId);

            return;
        }

        $action = $this->resolveMenuAction($user, $text);

        if ($action === null) {
            $this->telegram->sendMessage($chatId, $copy->get('menu.choose'), [
                'reply_markup' => $this->telegram->mainKeyboard($user),
            ]);

            return;
        }

        $this->runMenuAction($user, $chatId, $action);
    }

    /**
     * @param  array<string, mixed>  $update
     */
    protected function shouldProcessUpdate(array $update): bool
    {
        $updateId = $update['update_id'] ?? null;

        if ($updateId === null) {
            return true;
        }

        return Cache::add("telegram.update.{$updateId}", true, now()->addDay());
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    protected function handleCallback(array $callback): void
    {
        $data = (string) ($callback['data'] ?? '');
        $chatId = $callback['message']['chat']['id'] ?? null;
        $messageId = isset($callback['message']['message_id']) ? (int) $callback['message']['message_id'] : null;
        $from = $callback['from'] ?? [];
        $callbackId = (string) ($callback['id'] ?? '');

        if ($chatId === null || $callbackId === '') {
            return;
        }

        $user = $this->telegram->findOrCreateStudent(
            $from['id'],
            trim(($from['first_name'] ?? 'Student').' '.($from['last_name'] ?? '')),
            $from['username'] ?? null,
        );

        $copy = TelegramCopy::for($user);

        if (! $user->is_active) {
            $this->telegram->answerCallbackQuery($callbackId, $copy->get('menu.account_disabled'), true);

            return;
        }

        $alert = match (true) {
            str_starts_with($data, 'ob:') => $this->handleOnboardingCallback($user, $chatId, $data, $messageId),
            $data === 'setup:courses' => $this->handleSetupCourses($user, $chatId, $messageId),
            $data === 'start:continue', $data === 'continue:resume', $data === 'continue:switch' => $this->handleContinueCallback($user, $chatId, $data, $messageId),
            $data === 'browse' => $this->guardComplete($user, fn () => $this->showBrowse($user, $chatId, $messageId)),
            $data === 'back:courses' => $this->guardComplete($user, fn () => $this->showBrowse($user, $chatId, $messageId)),
            str_starts_with($data, 'course:') => $this->handleCourseTap($user, $chatId, (int) Str::after($data, 'course:'), $messageId),
            str_starts_with($data, 'course_resources:') => $this->handleCourseTap($user, $chatId, (int) Str::after($data, 'course_resources:'), $messageId),
            str_starts_with($data, 'hub:') => $this->handleHubCallback($user, $chatId, $data, $messageId),
            str_starts_with($data, 'open_resource:') => $this->handleOpenResource($user, $chatId, $data),
            $data === 'notify:on' => $this->handleNotifyOn($user, $chatId),
            $data === 'profile:notify' => $this->guardComplete($user, function () use ($user, $chatId): void {
                $this->toggleNotifications($user, $chatId);
            }),
            $data === 'profile:refer' => $this->guardComplete($user, function () use ($user, $chatId): void {
                $this->showReferrals($user, $chatId);
            }),
            $data === 'profile:settings' => $this->guardComplete($user, function () use ($user, $chatId, $messageId): void {
                $this->showSettings($user, $chatId, $messageId);
            }),
            str_starts_with($data, 'settings:lang:') => $this->handleLanguageSwitch($user, $chatId, Str::after($data, 'settings:lang:')),
            $data === 'premium_pay' => $this->handlePremiumPay($user, $chatId),
            $data === 'withdraw_request' => $this->handleWithdrawRequestCallback($user, $chatId),
            $this->isMenuOrSlashCommand($data) => $this->handleMenuCallback($user, $chatId, $data),
            default => $copy->get('menu.invalid_button'),
        };

        $this->telegram->answerCallbackQuery($callbackId, $alert, $alert !== null);
    }

    protected function guardComplete(User $user, callable $callback): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return TelegramCopy::for($user)->get('menu.finish_onboarding');
        }

        $callback();

        return null;
    }

    protected function handleSetupCourses(User $user, int|string $chatId, ?int $messageId = null): ?string
    {
        $user->update(['onboarding_step' => OnboardingStep::Stream]);
        $this->askStream($user, $chatId, $messageId);

        return null;
    }

    protected function handleContinueCallback(User $user, int|string $chatId, string $data, ?int $messageId): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return TelegramCopy::for($user)->get('menu.finish_onboarding');
        }

        if ($data === 'continue:switch') {
            $this->showBrowse($user, $chatId, $messageId);

            return null;
        }

        if ($data === 'continue:resume') {
            $download = $this->latestDownload($user);

            if ($download?->learningResource !== null) {
                $this->openResource($user, $chatId, $download->learningResource->id);

                return null;
            }
        }

        $this->showContinue($user, $chatId, $messageId);

        return null;
    }

    protected function handleCourseTap(User $user, int|string $chatId, int $courseId, ?int $messageId): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return TelegramCopy::for($user)->get('menu.finish_onboarding');
        }

        $this->showCourseHub($user, $chatId, $courseId, $messageId);

        return null;
    }

    protected function handleHubCallback(User $user, int|string $chatId, string $data, ?int $messageId): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return TelegramCopy::for($user)->get('menu.finish_onboarding');
        }

        $parts = explode(':', $data);

        if (count($parts) !== 3) {
            return TelegramCopy::for($user)->get('menu.invalid_button');
        }

        $hub = ResourceHub::tryFrom($parts[1]);
        $courseId = (int) $parts[2];

        if ($hub === null) {
            return TelegramCopy::for($user)->get('menu.invalid_button');
        }

        $this->listResources($user, $chatId, $courseId, $hub, $messageId);

        return null;
    }

    protected function handleOpenResource(User $user, int|string $chatId, string $data): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return TelegramCopy::for($user)->get('menu.finish_onboarding');
        }

        $resourceId = (int) Str::after($data, 'open_resource:');
        $this->openResource($user, $chatId, $resourceId);

        return null;
    }

    protected function handleNotifyOn(User $user, int|string $chatId): ?string
    {
        $copy = TelegramCopy::for($user);

        if ($user->notifications_enabled) {
            $this->telegram->sendMessage($chatId, $copy->get('notify.already'));

            return null;
        }

        $user->update(['notifications_enabled' => true]);
        $this->telegram->sendMessage($chatId, $copy->get('notify.enabled'));

        return null;
    }

    protected function handleLanguageSwitch(User $user, int|string $chatId, string $locale): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return TelegramCopy::for($user)->get('menu.finish_onboarding');
        }

        $resolved = TelegramLocale::tryFrom($locale) ?? TelegramLocale::English;
        $user->update(['telegram_locale' => $resolved->value]);
        $user->refresh();

        $copy = TelegramCopy::for($user);
        $this->telegram->sendMessage($chatId, $copy->get('settings.saved'), [
            'reply_markup' => $this->telegram->mainKeyboard($user),
        ]);

        return null;
    }

    protected function handlePremiumPay(User $user, int|string $chatId): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return TelegramCopy::for($user)->get('menu.finish_onboarding');
        }

        $payment = $this->payments->createPendingPremiumPayment($user);
        $this->telegram->sendMessage($chatId, $this->payments->instructionsFor($payment)."\n\nTap ⭐ Premium again after paying and wait for admin verification.");

        return null;
    }

    protected function handleWithdrawRequestCallback(User $user, int|string $chatId): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return TelegramCopy::for($user)->get('menu.finish_onboarding');
        }

        $this->handleWithdrawRequest($user, $chatId);

        return null;
    }

    protected function handleMenuCallback(User $user, int|string $chatId, string $data): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return TelegramCopy::for($user)->get('menu.finish_onboarding');
        }

        if (str_starts_with($data, '/start')) {
            $this->sendHome($user, $chatId);

            return null;
        }

        $action = $this->resolveMenuAction($user, $data);

        if ($action !== null) {
            $this->runMenuAction($user, $chatId, $action);
        }

        return null;
    }

    protected function handleWithdrawRequest(User $user, int|string $chatId): void
    {
        $balance = ReferralReward::query()
            ->where('user_id', $user->id)
            ->where('status', RewardStatus::Approved)
            ->sum('amount');

        if ($balance <= 0) {
            $this->telegram->sendMessage($chatId, 'No approved rewards available to withdraw.');

            return;
        }

        Withdrawal::query()->create([
            'user_id' => $user->id,
            'amount' => $balance,
            'method' => 'pending_details',
            'details' => 'Requested via Telegram',
            'status' => WithdrawalStatus::Pending,
        ]);

        ReferralReward::query()
            ->where('user_id', $user->id)
            ->where('status', RewardStatus::Approved)
            ->update(['status' => RewardStatus::Paid]);

        $this->telegram->sendMessage($chatId, "Withdrawal request submitted for {$balance} ETB.");
    }

    protected function isMenuOrSlashCommand(string $data): bool
    {
        if ($data === '' || str_contains($data, ':')) {
            return false;
        }

        return str_starts_with($data, '/') || array_key_exists($data, $this->menuLabelMap());
    }

    protected function resolveMenuAction(User $user, string $text): ?string
    {
        return $this->menuLabelMap()[$text] ?? null;
    }

    /**
     * @return array<string, string>
     */
    protected function menuLabelMap(): array
    {
        $map = [];

        foreach ([TelegramLocale::English, TelegramLocale::Amharic] as $locale) {
            $copy = new TelegramCopy($locale->value);
            $map[$copy->get('keyboard.continue')] = 'continue';
            $map[$copy->get('keyboard.browse')] = 'browse';
            $map[$copy->get('keyboard.profile')] = 'profile';
            $map[$copy->get('keyboard.premium')] = 'premium';
        }

        $map['📚 My Courses'] = 'browse';
        $map['📖 Resources'] = 'browse';
        $map['👤 My Profile'] = 'profile';
        $map['👥 Refer & Earn'] = 'refer';
        $map['🔔 Notifications'] = 'notify';
        $map['⭐ Premium'] = 'premium';

        return $map;
    }

    protected function runMenuAction(User $user, int|string $chatId, string $action): void
    {
        match ($action) {
            'continue' => $this->showContinue($user, $chatId),
            'browse' => $this->showBrowse($user, $chatId),
            'profile' => $this->showProfile($user, $chatId),
            'premium' => $this->showPremium($user, $chatId),
            'refer' => $this->showReferrals($user, $chatId),
            'notify' => $this->toggleNotifications($user, $chatId),
            default => null,
        };
    }

    protected function sendHome(User $user, int|string $chatId): void
    {
        $copy = TelegramCopy::for($user);
        $name = $copy->firstName($user);
        $hasCourses = $user->courses()->exists();

        if (! $hasCourses) {
            $this->telegram->sendMessage($chatId, $copy->get('start.new', ['name' => $name]), [
                'reply_markup' => [
                    'inline_keyboard' => [[
                        [
                            'text' => $copy->get('start.new_button'),
                            'callback_data' => 'setup:courses',
                            'style' => TelegramButtonStyle::Primary->value,
                        ],
                    ]],
                ],
            ]);
            $this->telegram->sendMessage($chatId, "\u2060", [
                'reply_markup' => $this->telegram->mainKeyboard($user),
            ]);

            return;
        }

        $this->telegram->sendMessage($chatId, $copy->get('start.returning', ['name' => $name]), [
            'reply_markup' => [
                'inline_keyboard' => [[
                    [
                        'text' => $copy->get('start.returning_button'),
                        'callback_data' => 'start:continue',
                        'style' => TelegramButtonStyle::Primary->value,
                    ],
                ]],
            ],
        ]);
        $this->telegram->sendMessage($chatId, "\u2060", [
            'reply_markup' => $this->telegram->mainKeyboard($user),
        ]);
    }

    protected function showContinue(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $download = $this->latestDownload($user);
        $resource = $download?->learningResource;

        if ($resource === null || ! $resource->is_published) {
            $this->showBrowse($user, $chatId, $messageId);

            return;
        }

        $copy = TelegramCopy::for($user);
        $course = $resource->course;
        $label = $resource->title;

        $this->telegram->replyOrEdit(
            $chatId,
            $copy->get('continue.title', [
                'course' => $course?->name ?? 'Course',
                'resource' => $label,
            ]),
            [
                'reply_markup' => $this->telegram->inlineKeyboard([[
                    [
                        'text' => $copy->get('continue.resume'),
                        'callback_data' => 'continue:resume',
                        'style' => TelegramButtonStyle::Primary->value,
                    ],
                    [
                        'text' => $copy->get('continue.switch'),
                        'callback_data' => 'continue:switch',
                    ],
                ]]),
            ],
            $messageId,
        );
    }

    protected function latestDownload(User $user): ?ResourceDownload
    {
        return $user->downloads()
            ->with(['learningResource.course'])
            ->latest('id')
            ->first();
    }

    /**
     * @return Collection<int, Course>
     */
    protected function enrolledCoursesWithPublishedCount(User $user): Collection
    {
        return $user->courses()
            ->withCount([
                'learningResources as published_resources_count' => fn ($query) => $query->published(),
            ])
            ->orderBy('name')
            ->get();
    }

    protected function showBrowse(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $copy = TelegramCopy::for($user);
        $courses = $this->enrolledCoursesWithPublishedCount($user);

        if ($courses->isEmpty()) {
            $this->telegram->replyOrEdit($chatId, $copy->get('browse.empty'), [
                'reply_markup' => $this->telegram->inlineKeyboard([[
                    [
                        'text' => $copy->get('browse.empty_button'),
                        'callback_data' => 'setup:courses',
                        'style' => TelegramButtonStyle::Primary->value,
                    ],
                ]]),
            ], $messageId);

            return;
        }

        $withContent = $courses->filter(fn (Course $course) => (int) $course->published_resources_count > 0)->values();

        if ($withContent->isEmpty()) {
            $rows = $courses->map(fn (Course $course) => [[
                'text' => $course->name,
                'callback_data' => "course:{$course->id}",
                'style' => TelegramButtonStyle::Primary->value,
            ]])->all();

            $rows[] = [[
                'text' => $copy->get('browse.notify'),
                'callback_data' => 'notify:on',
                'style' => TelegramButtonStyle::Success->value,
            ]];

            $this->telegram->replyOrEdit($chatId, $copy->get('browse.coming_soon'), [
                'reply_markup' => $this->telegram->inlineKeyboard($rows),
            ], $messageId);

            return;
        }

        $rows = $withContent->map(fn (Course $course) => [[
            'text' => $course->name,
            'callback_data' => "course:{$course->id}",
            'style' => TelegramButtonStyle::Primary->value,
        ]])->all();

        $this->telegram->replyOrEdit($chatId, $copy->get('browse.has_content'), [
            'reply_markup' => $this->telegram->inlineKeyboard($rows),
        ], $messageId);
    }

    protected function showCourseHub(User $user, int|string $chatId, int $courseId, ?int $messageId = null): void
    {
        $copy = TelegramCopy::for($user);
        $course = Course::query()->find($courseId);

        if ($course === null || ! $user->courses()->where('courses.id', $courseId)->exists()) {
            $this->showBrowse($user, $chatId, $messageId);

            return;
        }

        $hubButtons = [];

        foreach ([ResourceHub::Notes, ResourceHub::Modules, ResourceHub::Practice] as $hub) {
            $count = LearningResource::query()
                ->published()
                ->where('course_id', $courseId)
                ->whereIn('type', $hub->typeValues())
                ->count();

            if ($count === 0) {
                continue;
            }

            $labelKey = match ($hub) {
                ResourceHub::Notes => 'hub.notes',
                ResourceHub::Modules => 'hub.modules',
                ResourceHub::Practice => 'hub.quiz',
                ResourceHub::Exams => 'hub.quiz',
            };

            $hubButtons[] = [
                'text' => $copy->get($labelKey),
                'callback_data' => "hub:{$hub->value}:{$courseId}",
                'style' => TelegramButtonStyle::Primary->value,
            ];
        }

        if ($hubButtons === []) {
            $this->telegram->replyOrEdit(
                $chatId,
                $copy->get('browse.course_coming_soon', ['course' => $course->name]),
                [
                    'reply_markup' => $this->telegram->inlineKeyboard([
                        [[
                            'text' => $copy->get('browse.notify'),
                            'callback_data' => 'notify:on',
                            'style' => TelegramButtonStyle::Success->value,
                        ]],
                        [[
                            'text' => $copy->get('hub.back'),
                            'callback_data' => 'back:courses',
                        ]],
                    ]),
                ],
                $messageId,
            );

            return;
        }

        $this->telegram->replyOrEdit(
            $chatId,
            $copy->get('hub.prompt', ['course' => $course->name]),
            [
                'reply_markup' => $this->telegram->inlineKeyboard([
                    $hubButtons,
                    [[
                        'text' => $copy->get('hub.back'),
                        'callback_data' => 'back:courses',
                    ]],
                ]),
            ],
            $messageId,
        );
    }

    protected function listResources(
        User $user,
        int|string $chatId,
        int $courseId,
        ?ResourceHub $hub = null,
        ?int $messageId = null,
    ): void {
        $copy = TelegramCopy::for($user);

        $query = LearningResource::query()
            ->published()
            ->where('course_id', $courseId)
            ->orderBy('title');

        if ($hub !== null) {
            $query->whereIn('type', $hub->typeValues());
        }

        $resources = $query->get();

        if ($resources->isEmpty()) {
            $rows = [[['text' => $copy->get('hub.back'), 'callback_data' => $hub !== null ? "course:{$courseId}" : 'back:courses']]];
            $this->telegram->replyOrEdit($chatId, $copy->get('hub.no_resources'), [
                'reply_markup' => $this->telegram->inlineKeyboard($rows),
            ], $messageId);

            return;
        }

        $rows = $resources->map(fn (LearningResource $resource) => [[
            'text' => ($resource->is_premium ? '🔒 ' : '').$resource->title,
            'callback_data' => "open_resource:{$resource->id}",
            'style' => TelegramButtonStyle::Primary->value,
        ]])->values()->all();

        $rows[] = [[
            'text' => $copy->get('hub.back'),
            'callback_data' => $hub !== null ? "course:{$courseId}" : 'back:courses',
        ]];

        $this->telegram->replyOrEdit($chatId, $copy->get('hub.resources'), [
            'reply_markup' => $this->telegram->inlineKeyboard($rows),
        ], $messageId);
    }

    protected function openResource(User $user, int|string $chatId, int $resourceId): void
    {
        $copy = TelegramCopy::for($user);
        $resource = LearningResource::query()->published()->with('course')->find($resourceId);

        if ($resource === null) {
            $this->telegram->sendMessage($chatId, $copy->get('resource.not_found'));

            return;
        }

        if (! $this->premium->canAccess($user, $resource)) {
            $required = $this->settings->requiredReferrals();
            $progress = $this->referrals->qualifiedCount($user);
            $price = $this->settings->premiumPrice();
            $this->telegram->sendMessage($chatId, "🔒 <b>Premium Resource</b>\n\nUnlock for <b>{$price} ETB</b>\nOR refer <b>{$required}</b> students.\nProgress: <b>{$progress} / {$required}</b>");

            return;
        }

        $user->downloads()->create(['learning_resource_id' => $resource->id]);

        $chunks = app(TelegramResourceFormatter::class)->format($resource);

        foreach ($chunks as $chunk) {
            $this->telegram->sendMessage($chatId, $chunk);
        }

        $this->maybeSendQuizNudge($user, $chatId, $resource);
    }

    protected function maybeSendQuizNudge(User $user, int|string $chatId, LearningResource $resource): void
    {
        $course = $resource->course;

        if ($course === null) {
            return;
        }

        $isNotesOrModule = in_array($resource->type, [
            ResourceType::LectureNotes,
            ResourceType::Summary,
            ResourceType::Module,
        ], true);

        if (! $isNotesOrModule) {
            return;
        }

        $hasPractice = LearningResource::query()
            ->published()
            ->where('course_id', $course->id)
            ->whereIn('type', ResourceHub::Practice->typeValues())
            ->exists();

        if (! $hasPractice) {
            return;
        }

        $copy = TelegramCopy::for($user);

        $this->telegram->sendMessage(
            $chatId,
            $copy->get('quiz_nudge.text', ['course' => $course->name]),
            [
                'reply_markup' => $this->telegram->inlineKeyboard([[
                    [
                        'text' => $copy->get('quiz_nudge.button'),
                        'web_app' => [
                            'url' => route('hubs.course', ['hub' => ResourceHub::Practice->value, 'course' => $course]),
                        ],
                        'style' => TelegramButtonStyle::Success->value,
                    ],
                ]]),
            ],
        );
    }

    protected function showPremium(User $user, int|string $chatId): void
    {
        if ($user->hasActivePremium()) {
            $this->telegram->sendMessage($chatId, 'Premium active until '.$user->premium_until->toDayDateTimeString());

            return;
        }

        $price = $this->settings->premiumPrice();
        $required = $this->settings->requiredReferrals();
        $progress = $this->referrals->qualifiedCount($user);

        $this->telegram->sendMessage($chatId, "⭐ Premium\nPrice: {$price} ETB\nReferrals: {$progress}/{$required}", [
            'reply_markup' => $this->telegram->inlineKeyboard([[
                ['text' => 'Pay now', 'callback_data' => 'premium_pay', 'style' => TelegramButtonStyle::Primary->value],
            ]]),
        ]);
    }

    protected function showReferrals(User $user, int|string $chatId): void
    {
        $count = $this->referrals->qualifiedCount($user);
        $required = $this->settings->requiredReferrals();
        $link = $this->referrals->referralLink($user);
        $approved = ReferralReward::query()->where('user_id', $user->id)->where('status', RewardStatus::Approved)->sum('amount');

        $this->telegram->sendMessage($chatId, "👥 Refer & Earn\nProgress: {$count}/{$required}\nApproved balance: {$approved} ETB\nYour link:\n{$link}", [
            'reply_markup' => $this->telegram->inlineKeyboard([[
                ['text' => 'Request withdrawal', 'callback_data' => 'withdraw_request', 'style' => TelegramButtonStyle::Danger->value],
            ]]),
        ]);
    }

    protected function toggleNotifications(User $user, int|string $chatId): void
    {
        $user->update(['notifications_enabled' => ! $user->notifications_enabled]);
        $copy = TelegramCopy::for($user);
        $state = $user->notifications_enabled
            ? $copy->get('notify.on')
            : $copy->get('notify.off');

        $this->telegram->sendMessage($chatId, $copy->get('notify.toggled', ['state' => $state]));
    }

    protected function showProfile(User $user, int|string $chatId): void
    {
        $copy = TelegramCopy::for($user);
        $user->load(['stream', 'university', 'semester', 'courses']);
        $premium = $user->hasActivePremium()
            ? $copy->get('profile.premium_yes', ['until' => $user->premium_until])
            : $copy->get('profile.premium_no');
        $courses = $user->courses->pluck('name')->implode(', ') ?: $copy->get('profile.none');

        $this->telegram->sendMessage($chatId, $copy->get('profile.body', [
            'name' => $user->name,
            'stream' => $user->stream?->name ?? '-',
            'university' => $user->university?->name ?? '-',
            'semester' => $user->semester?->name ?? '-',
            'courses' => $courses,
            'premium' => $premium,
        ]), [
            'reply_markup' => $this->telegram->inlineKeyboard([[
                ['text' => $copy->get('profile.notifications'), 'callback_data' => 'profile:notify'],
                ['text' => $copy->get('profile.refer'), 'callback_data' => 'profile:refer'],
                ['text' => $copy->get('profile.settings'), 'callback_data' => 'profile:settings'],
            ]]),
        ]);
    }

    protected function showSettings(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $copy = TelegramCopy::for($user);

        $this->telegram->replyOrEdit($chatId, $copy->get('settings.prompt'), [
            'reply_markup' => $this->telegram->inlineKeyboard([[
                [
                    'text' => TelegramLocale::English->label(),
                    'callback_data' => 'settings:lang:en',
                    'style' => TelegramButtonStyle::Primary->value,
                ],
                [
                    'text' => TelegramLocale::Amharic->label(),
                    'callback_data' => 'settings:lang:am',
                    'style' => TelegramButtonStyle::Primary->value,
                ],
            ]]),
        ], $messageId);
    }

    protected function repromptOnboarding(User $user, int|string $chatId): void
    {
        $rateLimitKey = TelegramService::OnboardingPromptRateLimitKey.$user->id;

        if (Cache::has($rateLimitKey)) {
            return;
        }

        Cache::put($rateLimitKey, true, now()->addSeconds(3));

        $messageId = $this->telegram->cachedInlineMessageId($user->id);

        match ($user->onboarding_step) {
            OnboardingStep::Stream => $this->askStream($user, $chatId, $messageId),
            OnboardingStep::University => $this->askUniversity($user, $chatId, $messageId),
            OnboardingStep::Semester => $this->askSemester($user, $chatId, $messageId),
            OnboardingStep::Courses => $this->askCourses($user, $chatId, $messageId),
            default => $this->askStream($user, $chatId, $messageId),
        };
    }

    /**
     * @param  array<int, array<int, array<string, string>>>  $rows
     */
    protected function sendOnboardingPrompt(User $user, int|string $chatId, string $text, array $rows, ?int $messageId = null): void
    {
        $result = $this->telegram->replyOrEdit($chatId, $text, [
            'reply_markup' => $this->telegram->inlineKeyboard($rows),
        ], $messageId);

        $this->telegram->rememberInlineMessage($user->id, $result, $messageId);
    }

    protected function askStream(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $streams = Stream::query()->active()->orderBy('name')->get();

        if ($streams->isEmpty()) {
            $result = $this->telegram->replyOrEdit($chatId, 'No streams are available yet. Please try again later.', messageId: $messageId);
            $this->telegram->rememberInlineMessage($user->id, $result, $messageId);

            return;
        }

        $rows = $streams->map(fn (Stream $stream) => [[
            'text' => $stream->name,
            'callback_data' => "ob:stream:{$stream->id}",
            'style' => TelegramButtonStyle::Primary->value,
        ]])->values()->all();

        $this->sendOnboardingPrompt($user, $chatId, 'Tap your stream:', $rows, $messageId);
    }

    protected function askUniversity(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $unis = University::query()->active()->orderBy('sort_order')->orderBy('name')->get();

        $rows = $unis->map(fn (University $uni) => [[
            'text' => $uni->name,
            'callback_data' => "ob:uni:{$uni->id}",
            'style' => TelegramButtonStyle::Primary->value,
        ]])->values()->all();

        $rows[] = [['text' => 'Skip', 'callback_data' => 'ob:uni:skip', 'style' => TelegramButtonStyle::Danger->value]];

        $this->sendOnboardingPrompt($user, $chatId, 'Optional: tap your university, or Skip:', $rows, $messageId);
    }

    protected function askSemester(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $semesters = Semester::query()->orderBy('sort_order')->orderBy('name')->get();

        $rows = $semesters->map(fn (Semester $semester) => [[
            'text' => $semester->name,
            'callback_data' => "ob:sem:{$semester->id}",
            'style' => TelegramButtonStyle::Primary->value,
        ]])->values()->all();

        $rows[] = [['text' => 'Skip', 'callback_data' => 'ob:sem:skip', 'style' => TelegramButtonStyle::Danger->value]];

        $this->sendOnboardingPrompt($user, $chatId, 'Optional: tap your semester, or Skip:', $rows, $messageId);
    }

    protected function askCourses(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $user->loadMissing('stream');

        if ($user->stream === null) {
            $user->update(['onboarding_step' => OnboardingStep::Stream]);
            $this->askStream($user, $chatId, $messageId);

            return;
        }

        $selectedIds = collect(cache()->get("onboarding.courses.{$user->id}", []))->map(fn ($id) => (int) $id)->all();

        if ($selectedIds === []) {
            $recommended = $this->onboarding->recommendCourses($user->stream, $user->university_id);
            $selectedIds = $recommended->pluck('id')->map(fn ($id) => (int) $id)->all();
            cache()->put("onboarding.courses.{$user->id}", $selectedIds, now()->addHour());
        }

        $courses = $this->coursesForOnboarding($user, $selectedIds);

        if ($courses->isEmpty()) {
            $this->sendOnboardingPrompt($user, $chatId, 'No courses are available for your stream yet. Tap Confirm to finish.', [
                [['text' => 'Confirm', 'callback_data' => 'ob:course:confirm', 'style' => TelegramButtonStyle::Success->value]],
            ], $messageId);

            return;
        }

        $rows = $courses->map(function (Course $course) use ($selectedIds) {
            $prefix = in_array($course->id, $selectedIds, true) ? '✅ ' : '';

            return [[
                'text' => $prefix.$course->name,
                'callback_data' => "ob:course:toggle:{$course->id}",
                'style' => TelegramButtonStyle::Primary->value,
            ]];
        })->values()->all();

        $rows[] = [['text' => 'Confirm', 'callback_data' => 'ob:course:confirm', 'style' => TelegramButtonStyle::Success->value]];

        $this->sendOnboardingPrompt($user, $chatId, 'Tap courses to select or deselect, then Confirm:', $rows, $messageId);
    }

    /**
     * @param  array<int>  $selectedIds
     * @return Collection<int, Course>
     */
    protected function coursesForOnboarding(User $user, array $selectedIds): Collection
    {
        $recommended = $this->onboarding->recommendCourses($user->stream, $user->university_id);
        $extra = Course::query()
            ->active()
            ->where('stream_id', $user->stream_id)
            ->whereIn('id', $selectedIds)
            ->get();

        return $recommended->concat($extra)->unique('id')->values();
    }

    protected function handleOnboardingCallback(User $user, int|string $chatId, string $data, ?int $messageId): ?string
    {
        $copy = TelegramCopy::for($user);

        if ($user->onboarding_step === OnboardingStep::Complete) {
            return $copy->get('menu.onboarding_complete_menu');
        }

        if (str_starts_with($data, 'ob:stream:')) {
            return $this->selectStream($user, $chatId, (int) Str::after($data, 'ob:stream:'), $messageId);
        }

        if ($data === 'ob:uni:skip') {
            return $this->selectUniversity($user, $chatId, null, $messageId);
        }

        if (str_starts_with($data, 'ob:uni:')) {
            return $this->selectUniversity($user, $chatId, (int) Str::after($data, 'ob:uni:'), $messageId);
        }

        if ($data === 'ob:sem:skip') {
            return $this->selectSemester($user, $chatId, null, $messageId);
        }

        if (str_starts_with($data, 'ob:sem:')) {
            return $this->selectSemester($user, $chatId, (int) Str::after($data, 'ob:sem:'), $messageId);
        }

        if (str_starts_with($data, 'ob:course:toggle:')) {
            return $this->toggleCourse($user, $chatId, (int) Str::after($data, 'ob:course:toggle:'), $messageId);
        }

        if ($data === 'ob:course:confirm') {
            return $this->confirmCourses($user, $chatId, $messageId);
        }

        return 'That onboarding button is no longer valid.';
    }

    protected function selectStream(User $user, int|string $chatId, int $streamId, ?int $messageId): ?string
    {
        $copy = TelegramCopy::for($user);

        if ($user->onboarding_step !== OnboardingStep::Stream && $user->onboarding_step !== OnboardingStep::Start) {
            return $copy->get('menu.stale_callback');
        }

        $stream = Stream::query()->active()->find($streamId);

        if ($stream === null) {
            $this->askStream($user, $chatId, $messageId);

            return 'That stream is unavailable.';
        }

        $user->update([
            'stream_id' => $stream->id,
            'onboarding_step' => OnboardingStep::University,
        ]);

        $this->askUniversity($user, $chatId, $messageId);

        return null;
    }

    protected function selectUniversity(User $user, int|string $chatId, ?int $universityId, ?int $messageId): ?string
    {
        $copy = TelegramCopy::for($user);

        if ($user->onboarding_step !== OnboardingStep::University) {
            return $copy->get('menu.stale_callback');
        }

        if ($universityId !== null) {
            $uni = University::query()->active()->find($universityId);

            if ($uni === null) {
                $this->askUniversity($user, $chatId, $messageId);

                return 'That university is unavailable.';
            }

            $user->update(['university_id' => $uni->id]);
        }

        $user->update(['onboarding_step' => OnboardingStep::Semester]);
        $this->askSemester($user, $chatId, $messageId);

        return null;
    }

    protected function selectSemester(User $user, int|string $chatId, ?int $semesterId, ?int $messageId): ?string
    {
        $copy = TelegramCopy::for($user);

        if ($user->onboarding_step !== OnboardingStep::Semester) {
            return $copy->get('menu.stale_callback');
        }

        if ($semesterId !== null) {
            $semester = Semester::query()->find($semesterId);

            if ($semester === null) {
                $this->askSemester($user, $chatId, $messageId);

                return 'That semester is unavailable.';
            }

            $user->update(['semester_id' => $semester->id]);
        }

        $user->loadMissing('stream');
        $user->update(['onboarding_step' => OnboardingStep::Courses]);

        $recommended = $user->stream !== null
            ? $this->onboarding->recommendCourses($user->stream, $user->university_id)
            : collect();

        cache()->put(
            "onboarding.courses.{$user->id}",
            $recommended->pluck('id')->map(fn ($id) => (int) $id)->all(),
            now()->addHour(),
        );

        $this->askCourses($user->fresh(), $chatId, $messageId);

        return null;
    }

    protected function toggleCourse(User $user, int|string $chatId, int $courseId, ?int $messageId): ?string
    {
        $copy = TelegramCopy::for($user);

        if ($user->onboarding_step !== OnboardingStep::Courses) {
            return $copy->get('menu.stale_callback');
        }

        $course = Course::query()
            ->active()
            ->where('stream_id', $user->stream_id)
            ->find($courseId);

        if ($course === null) {
            $this->askCourses($user, $chatId, $messageId);

            return 'That course is unavailable.';
        }

        $selectedIds = collect(cache()->get("onboarding.courses.{$user->id}", []))
            ->map(fn ($id) => (int) $id)
            ->all();

        if (in_array($courseId, $selectedIds, true)) {
            $selectedIds = array_values(array_filter($selectedIds, fn (int $id) => $id !== $courseId));
        } else {
            $selectedIds[] = $courseId;
        }

        cache()->put("onboarding.courses.{$user->id}", $selectedIds, now()->addHour());
        $this->askCourses($user, $chatId, $messageId);

        return null;
    }

    protected function confirmCourses(User $user, int|string $chatId, ?int $messageId): ?string
    {
        $copy = TelegramCopy::for($user);

        if ($user->onboarding_step === OnboardingStep::Complete) {
            return $copy->get('menu.onboarding_complete_menu');
        }

        if ($user->onboarding_step !== OnboardingStep::Courses) {
            return $copy->get('menu.stale_callback');
        }

        $ids = collect(cache()->get("onboarding.courses.{$user->id}", []))
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->onboarding->syncCourses($user, $ids);
        $this->onboarding->complete($user);
        cache()->forget("onboarding.courses.{$user->id}");
        cache()->forget(TelegramService::InlineMessageCacheKey.$user->id);

        if ($messageId !== null) {
            $this->telegram->editMessageText($chatId, $messageId, $copy->get('menu.setup_finished'), [
                'reply_markup' => $this->telegram->inlineKeyboard([]),
            ]);
        }

        $this->telegram->sendMessage($chatId, $copy->get('menu.onboarding_done'), [
            'reply_markup' => $this->telegram->mainKeyboard($user),
        ]);

        return null;
    }
}
