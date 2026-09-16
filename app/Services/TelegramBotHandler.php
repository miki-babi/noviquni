<?php

namespace App\Services;

use App\Enums\OnboardingStep;
use App\Enums\RewardStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\ReferralReward;
use App\Models\Semester;
use App\Models\Stream;
use App\Models\University;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\College\TelegramResourceFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TelegramBotHandler
{
    protected const string StaleCallbackAlert = 'This button has expired. Tap /start to begin again.';

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

        if (! $user->is_active) {
            $this->telegram->sendMessage($chatId, 'Your account is disabled. Contact support.');

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

            $this->telegram->sendMessage($chatId, "Welcome back, {$user->name}!", [
                'reply_markup' => $this->telegram->mainKeyboard(),
            ]);

            return;
        }

        if ($user->onboarding_step !== OnboardingStep::Complete && $user->onboarding_step !== null) {
            $this->repromptOnboarding($user, $chatId);

            return;
        }

        match ($text) {
            '📚 My Courses' => $this->showCourses($user, $chatId),
            '📖 Resources' => $this->showCoursesForResources($user, $chatId),
            '⭐ Premium' => $this->showPremium($user, $chatId),
            '👥 Refer & Earn' => $this->showReferrals($user, $chatId),
            '🔔 Notifications' => $this->toggleNotifications($user, $chatId),
            '👤 My Profile' => $this->showProfile($user, $chatId),
            default => $this->telegram->sendMessage($chatId, 'Choose an option from the menu.', [
                'reply_markup' => $this->telegram->mainKeyboard(),
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $callback
     */
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

        if (! $user->is_active) {
            $this->telegram->answerCallbackQuery($callbackId, 'Your account is disabled. Contact support.', true);

            return;
        }

        $alert = match (true) {
            str_starts_with($data, 'ob:') => $this->handleOnboardingCallback($user, $chatId, $data, $messageId),
            $data === 'back:courses' => $this->handleBackToCourses($user, $chatId, $messageId),
            str_starts_with($data, 'course_resources:') => $this->handleCourseResources($user, $chatId, $data, $messageId),
            str_starts_with($data, 'open_resource:') => $this->handleOpenResource($user, $chatId, $data),
            $data === 'premium_pay' => $this->handlePremiumPay($user, $chatId),
            $data === 'withdraw_request' => $this->handleWithdrawRequestCallback($user, $chatId),
            $this->isMenuOrSlashCommand($data) => $this->handleMenuCallback($user, $chatId, $data),
            default => 'That button is no longer valid. Use the menu.',
        };

        $this->telegram->answerCallbackQuery($callbackId, $alert, $alert !== null);
    }

    protected function handleBackToCourses(User $user, int|string $chatId, ?int $messageId): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return 'Finish onboarding first. Tap /start.';
        }

        $this->showCoursesForResources($user, $chatId, $messageId);

        return null;
    }

    protected function handleCourseResources(User $user, int|string $chatId, string $data, ?int $messageId): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return 'Finish onboarding first. Tap /start.';
        }

        $courseId = (int) Str::after($data, 'course_resources:');
        $this->listResources($user, $chatId, $courseId, $messageId);

        return null;
    }

    protected function handleOpenResource(User $user, int|string $chatId, string $data): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return 'Finish onboarding first. Tap /start.';
        }

        $resourceId = (int) Str::after($data, 'open_resource:');
        $this->openResource($user, $chatId, $resourceId);

        return null;
    }

    protected function handlePremiumPay(User $user, int|string $chatId): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return 'Finish onboarding first. Tap /start.';
        }

        $payment = $this->payments->createPendingPremiumPayment($user);
        $this->telegram->sendMessage($chatId, $this->payments->instructionsFor($payment)."\n\nTap ⭐ Premium again after paying and wait for admin verification.");

        return null;
    }

    protected function handleWithdrawRequestCallback(User $user, int|string $chatId): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return 'Finish onboarding first. Tap /start.';
        }

        $this->handleWithdrawRequest($user, $chatId);

        return null;
    }

    protected function handleMenuCallback(User $user, int|string $chatId, string $data): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return 'Finish onboarding first. Tap /start.';
        }

        $this->dispatchMenuCommand($user, $chatId, $data);

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

        return str_starts_with($data, '/')
            || in_array($data, [
                '📚 My Courses',
                '📖 Resources',
                '⭐ Premium',
                '👥 Refer & Earn',
                '🔔 Notifications',
                '👤 My Profile',
            ], true);
    }

    protected function dispatchMenuCommand(User $user, int|string $chatId, string $text): void
    {
        if (str_starts_with($text, '/start')) {
            $this->telegram->sendMessage($chatId, "Welcome back, {$user->name}!", [
                'reply_markup' => $this->telegram->mainKeyboard(),
            ]);

            return;
        }

        match ($text) {
            '📚 My Courses' => $this->showCourses($user, $chatId),
            '📖 Resources' => $this->showCoursesForResources($user, $chatId),
            '⭐ Premium' => $this->showPremium($user, $chatId),
            '👥 Refer & Earn' => $this->showReferrals($user, $chatId),
            '🔔 Notifications' => $this->toggleNotifications($user, $chatId),
            '👤 My Profile' => $this->showProfile($user, $chatId),
            default => null,
        };
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
        ]])->values()->all();

        $this->sendOnboardingPrompt($user, $chatId, 'Tap your stream:', $rows, $messageId);
    }

    protected function askUniversity(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $unis = University::query()->active()->orderBy('sort_order')->orderBy('name')->get();

        $rows = $unis->map(fn (University $uni) => [[
            'text' => $uni->name,
            'callback_data' => "ob:uni:{$uni->id}",
        ]])->values()->all();

        $rows[] = [['text' => 'Skip', 'callback_data' => 'ob:uni:skip']];

        $this->sendOnboardingPrompt($user, $chatId, 'Optional: tap your university, or Skip:', $rows, $messageId);
    }

    protected function askSemester(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $semesters = Semester::query()->orderBy('sort_order')->orderBy('name')->get();

        $rows = $semesters->map(fn (Semester $semester) => [[
            'text' => $semester->name,
            'callback_data' => "ob:sem:{$semester->id}",
        ]])->values()->all();

        $rows[] = [['text' => 'Skip', 'callback_data' => 'ob:sem:skip']];

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
                [['text' => 'Confirm', 'callback_data' => 'ob:course:confirm']],
            ], $messageId);

            return;
        }

        $rows = $courses->map(function (Course $course) use ($selectedIds) {
            $prefix = in_array($course->id, $selectedIds, true) ? '✅ ' : '';

            return [[
                'text' => $prefix.$course->name,
                'callback_data' => "ob:course:toggle:{$course->id}",
            ]];
        })->values()->all();

        $rows[] = [['text' => 'Confirm', 'callback_data' => 'ob:course:confirm']];

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
        if ($user->onboarding_step === OnboardingStep::Complete) {
            return 'Onboarding is already complete. Use the menu.';
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
        if ($user->onboarding_step !== OnboardingStep::Stream && $user->onboarding_step !== OnboardingStep::Start) {
            return self::StaleCallbackAlert;
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
        if ($user->onboarding_step !== OnboardingStep::University) {
            return self::StaleCallbackAlert;
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
        if ($user->onboarding_step !== OnboardingStep::Semester) {
            return self::StaleCallbackAlert;
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
        if ($user->onboarding_step !== OnboardingStep::Courses) {
            return self::StaleCallbackAlert;
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
        if ($user->onboarding_step === OnboardingStep::Complete) {
            return 'Onboarding is already complete. Use the menu.';
        }

        if ($user->onboarding_step !== OnboardingStep::Courses) {
            return self::StaleCallbackAlert;
        }

        $ids = collect(cache()->get("onboarding.courses.{$user->id}", []))
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->onboarding->syncCourses($user, $ids);
        $this->onboarding->complete($user);
        cache()->forget("onboarding.courses.{$user->id}");
        cache()->forget(TelegramService::InlineMessageCacheKey.$user->id);

        if ($messageId !== null) {
            $this->telegram->editMessageText($chatId, $messageId, 'Setup finished.', [
                'reply_markup' => $this->telegram->inlineKeyboard([]),
            ]);
        }

        $this->telegram->sendMessage($chatId, 'Onboarding complete! Explore your courses.', [
            'reply_markup' => $this->telegram->mainKeyboard(),
        ]);

        return null;
    }

    protected function showCourses(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $courses = $user->courses()->withCount('learningResources')->get();

        if ($courses->isEmpty()) {
            $this->telegram->replyOrEdit($chatId, 'No courses selected yet.', messageId: $messageId);

            return;
        }

        $rows = $courses->map(fn (Course $course) => [[
            'text' => "{$course->name} ({$course->learning_resources_count})",
            'callback_data' => "course_resources:{$course->id}",
        ]])->values()->all();

        $this->telegram->replyOrEdit($chatId, 'Your courses — tap one to open resources:', [
            'reply_markup' => $this->telegram->inlineKeyboard($rows),
        ], $messageId);
    }

    protected function showCoursesForResources(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $courses = $user->courses()->get();

        if ($courses->isEmpty()) {
            $this->telegram->replyOrEdit(
                $chatId,
                'Select courses first. Tap /start if you still need to finish onboarding.',
                messageId: $messageId,
            );

            return;
        }

        $rows = $courses->map(fn (Course $course) => [[
            'text' => $course->name,
            'callback_data' => "course_resources:{$course->id}",
        ]])->values()->all();

        $this->telegram->replyOrEdit($chatId, 'Pick a course:', [
            'reply_markup' => $this->telegram->inlineKeyboard($rows),
        ], $messageId);
    }

    protected function listResources(User $user, int|string $chatId, int $courseId, ?int $messageId = null): void
    {
        $resources = LearningResource::query()
            ->published()
            ->where('course_id', $courseId)
            ->orderBy('title')
            ->get();

        if ($resources->isEmpty()) {
            $rows = [[['text' => '« Back', 'callback_data' => 'back:courses']]];
            $this->telegram->replyOrEdit($chatId, 'No published resources for this course yet.', [
                'reply_markup' => $this->telegram->inlineKeyboard($rows),
            ], $messageId);

            return;
        }

        $rows = $resources->map(fn (LearningResource $resource) => [[
            'text' => ($resource->is_premium ? '🔒 ' : '').$resource->title,
            'callback_data' => "open_resource:{$resource->id}",
        ]])->values()->all();

        $rows[] = [['text' => '« Back', 'callback_data' => 'back:courses']];

        $this->telegram->replyOrEdit($chatId, 'Resources:', [
            'reply_markup' => $this->telegram->inlineKeyboard($rows),
        ], $messageId);
    }

    protected function openResource(User $user, int|string $chatId, int $resourceId): void
    {
        $resource = LearningResource::query()->published()->find($resourceId);

        if ($resource === null) {
            $this->telegram->sendMessage($chatId, 'Resource not found.');

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
                ['text' => 'Pay now', 'callback_data' => 'premium_pay'],
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
                ['text' => 'Request withdrawal', 'callback_data' => 'withdraw_request'],
            ]]),
        ]);
    }

    protected function toggleNotifications(User $user, int|string $chatId): void
    {
        $user->update(['notifications_enabled' => ! $user->notifications_enabled]);
        $state = $user->notifications_enabled ? 'ON' : 'OFF';
        $this->telegram->sendMessage($chatId, "Notifications are now {$state}.");
    }

    protected function showProfile(User $user, int|string $chatId): void
    {
        $user->load(['stream', 'university', 'semester', 'courses']);
        $premium = $user->hasActivePremium() ? 'Yes until '.$user->premium_until : 'No';
        $courses = $user->courses->pluck('name')->implode(', ') ?: 'None';

        $this->telegram->sendMessage($chatId, "👤 {$user->name}\nStream: ".($user->stream?->name ?? '-')."\nUniversity: ".($user->university?->name ?? '-')."\nSemester: ".($user->semester?->name ?? '-')."\nCourses: {$courses}\nPremium: {$premium}");
    }
}
