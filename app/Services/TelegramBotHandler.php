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
use Illuminate\Support\Facades\Storage;
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
                $this->askStream($chatId);

                return;
            }

            $this->telegram->sendMessage($chatId, "Welcome back, {$user->name}!", [
                'reply_markup' => $this->telegram->mainKeyboard(),
            ]);

            return;
        }

        if ($user->onboarding_step !== OnboardingStep::Complete && $user->onboarding_step !== null) {
            $this->handleOnboarding($user, $chatId, $text);

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
    protected function handleCallback(array $callback): void
    {
        $data = $callback['data'] ?? '';
        $chatId = $callback['message']['chat']['id'] ?? null;
        $from = $callback['from'] ?? [];

        if ($chatId === null) {
            return;
        }

        $user = $this->telegram->findOrCreateStudent(
            $from['id'],
            trim(($from['first_name'] ?? 'Student').' '.($from['last_name'] ?? '')),
            $from['username'] ?? null,
        );

        if (str_starts_with($data, 'course_resources:')) {
            $courseId = (int) Str::after($data, 'course_resources:');
            $this->listResources($user, $chatId, $courseId);
        }

        if (str_starts_with($data, 'open_resource:')) {
            $resourceId = (int) Str::after($data, 'open_resource:');
            $this->openResource($user, $chatId, $resourceId);
        }

        if ($data === 'premium_pay') {
            $payment = $this->payments->createPendingPremiumPayment($user);
            $this->telegram->sendMessage($chatId, $this->payments->instructionsFor($payment)."\n\nTap ⭐ Premium again after paying and wait for admin verification.");
        }

        if ($data === 'withdraw_request') {
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

        if ($this->isMenuOrSlashCommand($data)) {
            $this->dispatchMenuCommand($user, $chatId, $data);
        }

        $this->telegram->call('answerCallbackQuery', [
            'callback_query_id' => $callback['id'],
        ]);
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

    protected function askStream(int|string $chatId): void
    {
        $streams = Stream::query()->active()->orderBy('name')->get();
        $lines = $streams->map(fn (Stream $s) => "• {$s->name}")->implode("\n");
        $this->telegram->sendMessage($chatId, "Choose your stream by typing the name:\n{$lines}");
    }

    protected function handleOnboarding(User $user, int|string $chatId, string $text): void
    {
        match ($user->onboarding_step) {
            OnboardingStep::Stream => $this->pickStream($user, $chatId, $text),
            OnboardingStep::University => $this->pickUniversity($user, $chatId, $text),
            OnboardingStep::Semester => $this->pickSemester($user, $chatId, $text),
            OnboardingStep::Courses => $this->pickCourses($user, $chatId, $text),
            default => $this->askStream($chatId),
        };
    }

    protected function pickStream(User $user, int|string $chatId, string $text): void
    {
        $stream = Stream::query()->active()->whereRaw('lower(name) = ?', [strtolower($text)])->first();

        if ($stream === null) {
            $this->askStream($chatId);

            return;
        }

        $user->update([
            'stream_id' => $stream->id,
            'onboarding_step' => OnboardingStep::University,
        ]);

        $unis = University::query()->active()->orderBy('sort_order')->get();
        $lines = $unis->map(fn (University $u) => "• {$u->name}")->implode("\n");
        $this->telegram->sendMessage($chatId, "Optional: choose your university (or type Skip):\n{$lines}");
    }

    protected function pickUniversity(User $user, int|string $chatId, string $text): void
    {
        if (strtolower($text) !== 'skip') {
            $uni = University::query()->active()->whereRaw('lower(name) = ?', [strtolower($text)])->first();
            if ($uni !== null) {
                $user->update(['university_id' => $uni->id]);
            }
        }

        $user->update(['onboarding_step' => OnboardingStep::Semester]);
        $semesters = Semester::query()->orderBy('sort_order')->get();
        $lines = $semesters->map(fn (Semester $s) => "• {$s->name}")->implode("\n");
        $this->telegram->sendMessage($chatId, "Optional: choose semester (or type Skip):\n{$lines}");
    }

    protected function pickSemester(User $user, int|string $chatId, string $text): void
    {
        if (strtolower($text) !== 'skip') {
            $semester = Semester::query()->whereRaw('lower(name) = ?', [strtolower($text)])->first();
            if ($semester !== null) {
                $user->update(['semester_id' => $semester->id]);
            }
        }

        $user->update(['onboarding_step' => OnboardingStep::Courses]);
        $recommended = $this->onboarding->recommendCourses($user->stream, $user->university_id);
        $lines = $recommended->map(fn (Course $c) => "• {$c->name}")->implode("\n");
        $this->telegram->sendMessage($chatId, "Recommended courses (type Accept or list course names separated by commas):\n{$lines}");
        cache()->put("onboarding.courses.{$user->id}", $recommended->pluck('id')->all(), now()->addHour());
    }

    protected function pickCourses(User $user, int|string $chatId, string $text): void
    {
        if (strtolower($text) === 'accept') {
            $ids = cache()->get("onboarding.courses.{$user->id}", []);
            $this->onboarding->syncCourses($user, $ids);
        } else {
            $names = collect(explode(',', $text))->map(fn ($n) => strtolower(trim($n)))->filter();
            $ids = Course::query()
                ->where('stream_id', $user->stream_id)
                ->get()
                ->filter(fn (Course $c) => $names->contains(strtolower($c->name)))
                ->pluck('id')
                ->all();
            $this->onboarding->syncCourses($user, $ids);
        }

        $this->onboarding->complete($user);
        $this->telegram->sendMessage($chatId, 'Onboarding complete! Explore your courses.', [
            'reply_markup' => $this->telegram->mainKeyboard(),
        ]);
    }

    protected function showCourses(User $user, int|string $chatId): void
    {
        $courses = $user->courses()->withCount('learningResources')->get();

        if ($courses->isEmpty()) {
            $this->telegram->sendMessage($chatId, 'No courses selected yet.');

            return;
        }

        $lines = $courses->map(fn (Course $c) => "• {$c->name} ({$c->learning_resources_count} resources)")->implode("\n");
        $this->telegram->sendMessage($chatId, "Your courses:\n{$lines}");
    }

    protected function showCoursesForResources(User $user, int|string $chatId): void
    {
        $courses = $user->courses()->get();

        if ($courses->isEmpty()) {
            $this->telegram->sendMessage($chatId, 'Select courses first from My Courses / re-onboard with /start.');

            return;
        }

        $buttons = $courses->map(fn (Course $c) => [[
            'text' => $c->name,
            'callback_data' => "course_resources:{$c->id}",
        ]])->values()->all();

        $this->telegram->sendMessage($chatId, 'Pick a course:', [
            'reply_markup' => ['inline_keyboard' => $buttons],
        ]);
    }

    protected function listResources(User $user, int|string $chatId, int $courseId): void
    {
        $resources = LearningResource::query()
            ->published()
            ->where('course_id', $courseId)
            ->orderBy('title')
            ->get();

        if ($resources->isEmpty()) {
            $this->telegram->sendMessage($chatId, 'No published resources for this course yet.');

            return;
        }

        $buttons = $resources->map(fn (LearningResource $r) => [[
            'text' => ($r->is_premium ? '🔒 ' : '').$r->title,
            'callback_data' => "open_resource:{$r->id}",
        ]])->values()->all();

        $this->telegram->sendMessage($chatId, 'Resources:', [
            'reply_markup' => ['inline_keyboard' => $buttons],
        ]);
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

        if (filled($resource->file_path)) {
            $url = Storage::disk(config('filesystems.default'))->url($resource->file_path);
            $this->telegram->sendDocument($chatId, $url, $resource->title);
        } else {
            $this->telegram->sendMessage($chatId, "<b>{$resource->title}</b>\n\n".($resource->description ?? 'No file attached.'));
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
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => 'Pay now', 'callback_data' => 'premium_pay'],
                ]],
            ],
        ]);
    }

    protected function showReferrals(User $user, int|string $chatId): void
    {
        $count = $this->referrals->qualifiedCount($user);
        $required = $this->settings->requiredReferrals();
        $link = $this->referrals->referralLink($user);
        $approved = ReferralReward::query()->where('user_id', $user->id)->where('status', RewardStatus::Approved)->sum('amount');

        $this->telegram->sendMessage($chatId, "👥 Refer & Earn\nProgress: {$count}/{$required}\nApproved balance: {$approved} ETB\nYour link:\n{$link}", [
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => 'Request withdrawal', 'callback_data' => 'withdraw_request'],
                ]],
            ],
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
