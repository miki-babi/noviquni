<?php

namespace App\Services;

use App\Enums\ChallengeStatus;
use App\Enums\OnboardingStep;
use App\Enums\OpportunityType;
use App\Enums\ResourceHub;
use App\Enums\ResourceType;
use App\Enums\TelegramButtonStyle;
use App\Enums\TelegramLocale;
use App\Enums\YearSlug;
use App\Models\Bookmark;
use App\Models\Broadcast;
use App\Models\Challenge;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Opportunity;
use App\Models\ResourceDownload;
use App\Models\Stream;
use App\Models\TelegramCommand;
use App\Models\TelegramFileAsset;
use App\Models\User;
use App\Models\Year;
use App\Support\LearningResourceFiles;
use App\Support\TelegramCopy;
use App\Support\TelegramHtml;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
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
        public ChallengeService $challenges,
        public OpportunityGuidanceService $opportunityGuidance,
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

        if ($this->tryHandleAdminDocument($message)) {
            return;
        }

        if ($this->tryHandleAdminPublishTitle($message)) {
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
            Context::add('telegram_command', 'start');

            $parts = explode(' ', $text, 2);
            $payload = filled($parts[1] ?? null) ? trim((string) $parts[1]) : null;

            if (filled($payload) && $user->referred_by_user_id === null) {
                $opportunityShare = app(TelegramDeepLink::class)->parseOpportunitySharePayload($payload);

                if ($opportunityShare !== null) {
                    if ($user->wasRecentlyCreated) {
                        $this->referrals->attributeReferral($user, $opportunityShare['referral_code']);
                        $user->refresh();
                    }
                } elseif (! $this->isNavigationStartPayload($payload)) {
                    $this->referrals->attributeReferral($user, $payload);
                    $user->refresh();
                }
            }

            if ($user->onboarding_step !== OnboardingStep::Complete) {
                $isOpportunityShare = app(TelegramDeepLink::class)->parseOpportunitySharePayload($payload) !== null;

                if ($this->isNavigationStartPayload($payload) && ! $isOpportunityShare) {
                    Cache::put($this->pendingStartCacheKey($user), $payload, now()->addDay());
                }

                if ($this->canAutoCompleteOnboarding($user)) {
                    $this->onboarding->complete($user);
                    $user->refresh();

                    if ($this->resumeStartPayload($user, $chatId, $payload)) {
                        return;
                    }

                    $this->sendHome($user, $chatId);

                    return;
                }

                if ($isOpportunityShare && $this->resumeStartPayload($user, $chatId, $payload)) {
                    return;
                }

                if ($this->settings->hasCustomTelegramStartMessage()) {
                    $this->sendCustomStartMessage($user, $chatId);
                }

                $this->promptCurrentOnboardingStep($user, $chatId);

                return;
            }

            if ($this->resumeStartPayload($user, $chatId, $payload)) {
                return;
            }

            $this->sendHome($user, $chatId);

            return;
        }

        if ($user->onboarding_step === OnboardingStep::Department) {
            $this->handleDepartmentReply($user, $chatId, $text);

            return;
        }

        if ($user->onboarding_step !== OnboardingStep::Complete && $user->onboarding_step !== null) {
            $this->repromptOnboarding($user, $chatId);

            return;
        }

        $this->handleTextReply($user, $chatId, $text);
    }

    protected function handleTextReply(User $user, int|string $chatId, string $text): void
    {
        if ($this->tryHandleCustomCommand($user, $chatId, $text)) {
            return;
        }

        if ($text === $this->telegram->courseKeyboardBackLabel()) {
            if (Cache::pull($this->resourceTypeSelectionCacheKey($user)) !== null) {
                $this->showResourceKeyboard($user, $chatId);

                return;
            }

            $this->sendReplyKeyboard($user, $chatId);

            return;
        }

        $savedTab = $this->telegram->savedTabForKeyboardLabel($user, $text);

        if ($savedTab !== null) {
            $this->showSaved($user, $chatId, tab: $savedTab);

            return;
        }

        $resourceType = $this->telegram->resourceTypeForKeyboardLabel($user, $text);

        if ($resourceType !== null) {
            $this->showCoursesForResourceType($user, $chatId, $resourceType);

            return;
        }

        $selectedResourceType = ResourceType::tryFrom((string) Cache::get($this->resourceTypeSelectionCacheKey($user)));
        $course = $user->courses()
            ->active()
            ->when(
                $selectedResourceType !== null,
                fn ($query) => $query->whereHas(
                    'learningResources',
                    fn ($resourceQuery) => $resourceQuery->published()->where('type', $selectedResourceType->value),
                ),
            )
            ->where('courses.name', $this->courseNameFromKeyboardLabel($text))
            ->first();

        if ($course !== null) {
            if ($selectedResourceType !== null) {
                Cache::forget($this->resourceTypeSelectionCacheKey($user));
                $this->deliverCourseResourceType($user, $chatId, $course, $selectedResourceType);

                return;
            }

            $this->showCourseHub($user, $chatId, $course->id);

            return;
        }

        $action = $this->resolveMenuAction($user, $text);

        if ($action === null) {
            $this->telegram->sendMessage($chatId, TelegramCopy::for($user)->get('menu.choose'), [
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

        if (str_starts_with($data, 'admin:publish:') || str_starts_with($data, 'admin:pub:')) {
            $alert = $this->handleAdminPublishCallback($from, $chatId, $data, $messageId);
            $this->telegram->answerCallbackQuery($callbackId, $alert, $alert !== null);

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
            $data === 'back:library' => $this->guardComplete($user, fn () => $this->showResourceLibrary($user, $chatId, $messageId)),
            str_starts_with($data, 'course:') => $this->handleCourseTap($user, $chatId, (int) Str::after($data, 'course:'), $messageId),
            str_starts_with($data, 'course_resources:') => $this->handleCourseTap($user, $chatId, (int) Str::after($data, 'course_resources:'), $messageId),
            str_starts_with($data, 'hub:') => $this->handleHubCallback($user, $chatId, $data, $messageId),
            str_starts_with($data, 'type_lib:') => $this->handleLibraryTypeCallback($user, $chatId, $data, $messageId),
            str_starts_with($data, 'hub_lib:') => $this->handleLibraryTypeCallback($user, $chatId, $data, $messageId),
            str_starts_with($data, 'rtype:') => $this->handleResourceTypeCallback($user, $chatId, $data, $messageId),
            str_starts_with($data, 'open_resource:') => $this->handleOpenResource($user, $chatId, $data),
            str_starts_with($data, 'save:resource:') => $this->handleSaveResource(
                $user,
                $chatId,
                (int) Str::after($data, 'save:resource:'),
                $messageId,
            ),
            str_starts_with($data, 'save:opp:') => $this->handleSaveOpportunity(
                $user,
                $chatId,
                (int) Str::after($data, 'save:opp:'),
                $messageId,
            ),
            str_starts_with($data, 'opp:guidance:') => $this->handleOpportunityGuidanceRequest(
                $user,
                $chatId,
                (int) Str::after($data, 'opp:guidance:'),
            ),
            str_starts_with($data, 'opp:apply:') => $this->handleOpportunityApply(
                $user,
                $chatId,
                (int) Str::after($data, 'opp:apply:'),
            ),
            str_starts_with($data, 'open_opp:') => $this->showOpportunity(
                $user,
                $chatId,
                (int) Str::after($data, 'open_opp:'),
                $messageId,
            ),
            str_starts_with($data, 'opp:page:') => $this->guardComplete(
                $user,
                function () use ($user, $chatId, $data, $messageId): void {
                    $parts = explode(':', Str::after($data, 'opp:page:'), 2);
                    $type = OpportunityType::tryFrom($parts[0] ?? '');
                    $page = (int) ($parts[1] ?? 0);

                    if ($type === null) {
                        return;
                    }

                    $this->showOpportunities($user, $chatId, $type, $messageId, $page);
                },
            ),
            str_starts_with($data, 'saved:page:') => $this->guardComplete(
                $user,
                function () use ($user, $chatId, $data, $messageId): void {
                    $remainder = Str::after($data, 'saved:page:');

                    if (str_contains($remainder, ':')) {
                        [$tab, $page] = explode(':', $remainder, 2);
                        $this->showSaved($user, $chatId, $messageId, $tab, (int) $page);

                        return;
                    }

                    $this->showSaved($user, $chatId, $messageId, null, (int) $remainder);
                },
            ),
            $data === 'notify:on' => $this->handleNotifyOn($user, $chatId),
            $data === 'profile:notify' => $this->guardComplete($user, function () use ($user, $chatId): void {
                $this->toggleNotifications($user, $chatId);
            }),
            $data === 'profile:refer' => $this->guardComplete($user, function () use ($user, $chatId): void {
                $this->showReferrals($user, $chatId);
            }),
            $data === 'challenges' => $this->guardComplete($user, function () use ($user, $chatId, $messageId): void {
                $this->showChallenges($user, $chatId, $messageId);
            }),
            str_starts_with($data, 'challenge:') => $this->guardComplete($user, function () use ($user, $chatId, $data, $messageId): void {
                $this->showChallenge($user, $chatId, (int) Str::after($data, 'challenge:'), $messageId);
            }),
            $data === 'profile:settings' => $this->guardComplete($user, function () use ($user, $chatId, $messageId): void {
                $this->showSettings($user, $chatId, $messageId);
            }),
            str_starts_with($data, 'settings:lang:') => $this->handleLanguageSwitch($user, $chatId, Str::after($data, 'settings:lang:')),
            $data === 'premium_pay' => $this->handlePremiumPay($user, $chatId),
            str_starts_with($data, BroadcastService::InlineChoiceBackCallbackPrefix) => $this->guardComplete(
                $user,
                fn (): ?string => $this->handleBroadcastChoiceBack($user, $chatId, $data, $messageId),
            ),
            str_starts_with($data, BroadcastService::InlineChoiceChangeCallbackPrefix) => $this->guardComplete(
                $user,
                fn (): ?string => $this->handleBroadcastChoiceChange($user, $chatId, $data, $messageId),
            ),
            str_starts_with($data, BroadcastService::InlineChoiceCallbackPrefix) => $this->guardComplete(
                $user,
                fn (): ?string => $this->handleBroadcastChoice($user, $chatId, $data, $messageId),
            ),
            str_starts_with($data, BroadcastService::InlineTextReplyCallbackPrefix) => $this->guardComplete(
                $user,
                function () use ($user, $chatId, $data): void {
                    $this->handleTextReply(
                        $user,
                        $chatId,
                        Str::after($data, BroadcastService::InlineTextReplyCallbackPrefix),
                    );
                },
            ),
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

        $result = $callback();

        return is_string($result) ? $result : null;
    }

    protected function handleBroadcastChoice(
        User $user,
        int|string $chatId,
        string $data,
        ?int $messageId,
    ): ?string {
        $broadcasts = app(BroadcastService::class);
        $parsed = $broadcasts->parseChoiceCallback($data);

        if ($parsed === null) {
            return TelegramCopy::for($user)->get('menu.invalid_button');
        }

        return $this->deliverBroadcastChoiceAnswer(
            $user,
            $chatId,
            $parsed['broadcast_id'],
            $parsed['choice_index'],
            $messageId,
        );
    }

    protected function handleBroadcastChoiceBack(
        User $user,
        int|string $chatId,
        string $data,
        ?int $messageId,
    ): ?string {
        if ($messageId === null) {
            return TelegramCopy::for($user)->get('menu.invalid_button');
        }

        $broadcasts = app(BroadcastService::class);
        $parsed = $broadcasts->parseChoiceBackCallback($data);

        if ($parsed === null) {
            return TelegramCopy::for($user)->get('menu.invalid_button');
        }

        $broadcast = Broadcast::query()->find($parsed['broadcast_id']);

        if ($broadcast === null) {
            return TelegramCopy::for($user)->get('menu.invalid_button');
        }

        $replyMarkup = $broadcasts->inlineKeyboard($broadcast->buttons, $broadcast);

        if ($replyMarkup === null) {
            return TelegramCopy::for($user)->get('menu.invalid_button');
        }

        $this->telegram->editMessageText(
            $chatId,
            $messageId,
            $broadcasts->personalize($broadcast->body, $user),
            ['reply_markup' => $replyMarkup],
        );

        return null;
    }

    protected function handleBroadcastChoiceChange(
        User $user,
        int|string $chatId,
        string $data,
        ?int $messageId,
    ): ?string {
        $broadcasts = app(BroadcastService::class);
        $parsed = $broadcasts->parseChoiceChangeCallback($data);

        if ($parsed === null) {
            return TelegramCopy::for($user)->get('menu.invalid_button');
        }

        return $this->deliverBroadcastChoiceAnswer(
            $user,
            $chatId,
            $parsed['broadcast_id'],
            $parsed['choice_index'],
            $messageId,
        );
    }

    protected function deliverBroadcastChoiceAnswer(
        User $user,
        int|string $chatId,
        int $broadcastId,
        int $choiceIndex,
        ?int $messageId,
    ): ?string {
        $broadcasts = app(BroadcastService::class);
        $broadcast = Broadcast::query()->find($broadcastId);

        if ($broadcast === null) {
            return TelegramCopy::for($user)->get('menu.invalid_button');
        }

        $response = $broadcasts->choiceResponse($broadcast, $choiceIndex);

        if ($response === null) {
            return TelegramCopy::for($user)->get('menu.invalid_button');
        }

        $copy = TelegramCopy::for($user);
        $text = $broadcasts->personalize($response, $user);
        $payload = [
            'reply_markup' => $broadcasts->choiceAnswerKeyboard(
                $broadcast,
                $copy->get('broadcast.choice_back'),
            ),
        ];

        if ($messageId !== null) {
            $this->telegram->editMessageText($chatId, $messageId, $text, $payload);
        } else {
            $this->telegram->sendMessage($chatId, $text, $payload);
        }

        return null;
    }

    protected function handleSetupCourses(User $user, int|string $chatId, ?int $messageId = null): ?string
    {
        return $this->guardComplete($user, function () use ($user, $chatId): void {
            $this->sendMiniAppOpen($user, $chatId, 'profile_edit', 'tg.profile.edit');
        });
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

    protected function handleResourceTypeCallback(User $user, int|string $chatId, string $data, ?int $messageId): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return TelegramCopy::for($user)->get('menu.finish_onboarding');
        }

        $parts = explode(':', $data);

        if (count($parts) !== 3) {
            return TelegramCopy::for($user)->get('menu.invalid_button');
        }

        $type = ResourceType::tryFrom($parts[1]);
        $courseId = (int) $parts[2];

        if ($type === null) {
            return TelegramCopy::for($user)->get('menu.invalid_button');
        }

        $this->listResourcesByType($user, $chatId, $courseId, $type, $messageId);

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

    protected function handleSaveResource(User $user, int|string $chatId, int $resourceId, ?int $messageId = null): ?string
    {
        $copy = TelegramCopy::for($user);

        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return $copy->get('menu.finish_onboarding');
        }

        $resource = LearningResource::query()->published()->find($resourceId);

        if ($resource === null) {
            return $copy->get('resource.not_found');
        }

        $existing = $user->bookmarks()
            ->whereMorphedTo('bookmarkable', $resource)
            ->first();

        if ($existing !== null) {
            $existing->delete();
            $status = $copy->get('saved.removed_status');
        } else {
            $user->bookmarks()->create([
                'bookmarkable_type' => $resource->getMorphClass(),
                'bookmarkable_id' => $resource->id,
            ]);
            $status = $copy->get('saved.saved_status');
        }

        if ($messageId !== null) {
            $this->telegram->editMessageReplyMarkup(
                $chatId,
                $messageId,
                $this->resourceFileKeyboard($user, $resource),
            );
        }

        return $status;
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
        if (! $this->premium->isEnabled()) {
            return null;
        }

        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return TelegramCopy::for($user)->get('menu.finish_onboarding');
        }

        $payment = $this->payments->createPendingPremiumPayment($user);
        $this->telegram->sendMessage($chatId, $this->payments->instructionsFor($payment)."\n\nTap ⭐ Premium again after paying and wait for admin verification.");

        return null;
    }

    protected function handleMenuCallback(User $user, int|string $chatId, string $data): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return TelegramCopy::for($user)->get('menu.finish_onboarding');
        }

        if (str_starts_with($data, '/start')) {
            Context::add('telegram_command', 'start');
            $this->sendHome($user, $chatId);

            return null;
        }

        if ($this->tryHandleCustomCommandCallback($user, $chatId, $data)) {
            return null;
        }

        $action = $this->resolveMenuAction($user, $data);

        if ($action !== null) {
            $this->runMenuAction($user, $chatId, $action);
        }

        return null;
    }

    protected function tryHandleCustomCommandCallback(User $user, int|string $chatId, string $data): bool
    {
        if (str_starts_with($data, '/')) {
            return $this->tryHandleCustomCommand($user, $chatId, $data);
        }

        $slug = TelegramCommand::normalizeCommand($data);

        if ($slug === '' || TelegramCommand::isReservedCommand($slug) || ! preg_match('/^[a-z0-9_]{1,32}$/', $slug)) {
            return false;
        }

        $command = TelegramCommand::query()
            ->active()
            ->where('command', $slug)
            ->with(['fileAssets'])
            ->first();

        if ($command === null) {
            return false;
        }

        Context::add('telegram_command', $command->command);
        $this->sendCustomCommandResponse($user, $chatId, $command);

        return true;
    }

    protected function isMenuOrSlashCommand(string $data): bool
    {
        if ($data === '' || str_contains($data, ':')) {
            return false;
        }

        if (str_starts_with($data, '/') || array_key_exists($data, $this->menuLabelMap())) {
            return true;
        }

        $slug = TelegramCommand::normalizeCommand($data);

        if ($slug === '' || TelegramCommand::isReservedCommand($slug) || ! preg_match('/^[a-z0-9_]{1,32}$/', $slug)) {
            return false;
        }

        return TelegramCommand::query()->active()->where('command', $slug)->exists();
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
            $map[$copy->get('keyboard.courses')] = 'courses';
            $map[$copy->get('keyboard.resources')] = 'resources';
            $map[$copy->get('keyboard.scholarships')] = 'scholarships';
            $map[$copy->get('keyboard.challenges')] = 'challenges';
            $map[$copy->get('keyboard.internships')] = 'internships';
            $map[$copy->get('keyboard.opportunities')] = 'opportunities';
            $map[$copy->get('keyboard.mentorship')] = 'mentorship';
            $map[$copy->get('keyboard.saved')] = 'saved';
            $map[$copy->get('keyboard.profile')] = 'profile';
            $map[$copy->get('keyboard.refer')] = 'refer';
            // Legacy reply-keyboard labels until users refresh via /start.
            $map[$copy->get('keyboard.resources_legacy')] = 'resources';
            $map[$copy->get('keyboard.scholarships_legacy')] = 'scholarships';
            $map[$copy->get('keyboard.refer_legacy')] = 'refer';
            $map[$copy->get('keyboard.continue')] = 'continue';
            $map[$copy->get('keyboard.browse')] = 'courses';
            $map[$copy->get('keyboard.premium')] = 'premium';
        }

        $map['📚 My Courses'] = 'courses';
        $map['📖 Resources'] = 'resources';
        $map['🏆 Challenges'] = 'challenges';
        $map['👤 My Profile'] = 'profile';
        $map['👥 Refer'] = 'refer';
        $map['👥 Refer & Earn'] = 'refer';
        $map['👥 Refer and earn'] = 'refer';
        $map['🔔 Notifications'] = 'notify';
        $map['⭐ Premium'] = 'premium';

        return $map;
    }

    protected function runMenuAction(User $user, int|string $chatId, string $action): void
    {
        match ($action) {
            'courses', 'browse' => $this->showCourseKeyboard($user, $chatId),
            'resources' => $this->showResourceKeyboard($user, $chatId),
            'saved' => $this->showSaved($user, $chatId),
            'challenges' => $this->showChallenges($user, $chatId),
            'profile' => $this->sendMiniAppOpen($user, $chatId, 'profile', 'tg.profile'),
            'continue' => $this->showContinue($user, $chatId),
            'premium' => $this->showPremium($user, $chatId),
            'refer' => $this->showReferrals($user, $chatId),
            'notify' => $this->toggleNotifications($user, $chatId),
            'scholarships', 'internships', 'opportunities', 'mentorship' => $this->showOpportunities(
                $user,
                $chatId,
                OpportunityType::fromMenuAction($action) ?? OpportunityType::Job,
            ),
            default => null,
        };
    }

    protected function showComingSoon(User $user, int|string $chatId): void
    {
        $this->telegram->sendMessage($chatId, TelegramCopy::for($user)->get('menu.coming_soon'), [
            'reply_markup' => $this->telegram->mainKeyboard($user),
        ]);
    }

    protected function showCourseKeyboard(User $user, int|string $chatId): void
    {
        $courses = $user->courses()->active()->exists();

        if (! $courses) {
            $this->showBrowse($user, $chatId);

            return;
        }

        $this->telegram->sendMessage($chatId, TelegramCopy::for($user)->get('browse.has_content'), [
            'reply_markup' => $this->telegram->courseKeyboard($user),
        ]);
    }

    protected function showResourceKeyboard(User $user, int|string $chatId): void
    {
        $hasResources = LearningResource::query()
            ->published()
            ->whereIn('course_id', $user->courses()->active()->select('courses.id'))
            ->exists();

        if (! $hasResources) {
            $this->showResourceLibrary($user, $chatId);

            return;
        }

        $this->telegram->sendMessage($chatId, TelegramCopy::for($user)->get('library.title'), [
            'reply_markup' => $this->telegram->resourceTypeKeyboard($user),
        ]);
    }

    protected function showCoursesForResourceType(User $user, int|string $chatId, ResourceType $type): void
    {
        Cache::put($this->resourceTypeSelectionCacheKey($user), $type->value, now()->addMinutes(15));

        $this->telegram->sendMessage($chatId, 'Choose a course for '.$type->label().':', [
            'reply_markup' => $this->telegram->courseKeyboard($user, $type),
        ]);
    }

    protected function deliverCourseResourceType(User $user, int|string $chatId, Course $course, ResourceType $type): void
    {
        $resources = LearningResource::query()
            ->published()
            ->where('course_id', $course->id)
            ->where('type', $type)
            ->orderBy('title')
            ->get();

        if ($resources->count() === 1 && $resources->first()->hasFiles()) {
            $this->openResource($user, $chatId, (int) $resources->first()->id);

            return;
        }

        $this->listResourcesByType($user, $chatId, $course->id, $type);
    }

    protected function resourceTypeSelectionCacheKey(User $user): string
    {
        return 'telegram.resource_hub_selection.'.$user->id;
    }

    protected function courseNameFromKeyboardLabel(string $text): string
    {
        return str_starts_with($text, '📗 ')
            ? substr($text, strlen('📗 '))
            : '';
    }

    protected function sendMiniAppOpen(User $user, int|string $chatId, string $destination, string $routeName): void
    {
        $copy = TelegramCopy::for($user);

        $this->telegram->sendMessage($chatId, $copy->get('mini_app.open_prompt.'.$destination), [
            'reply_markup' => $this->telegram->inlineKeyboard([[
                [
                    'text' => $copy->get('mini_app.open_button.'.$destination),
                    'web_app' => ['url' => $this->telegram->miniAppUrl($routeName)],
                    'style' => TelegramButtonStyle::Success->value,
                ],
            ]]),
        ]);
    }

    protected function sendReplyKeyboard(User $user, int|string $chatId): void
    {
        $this->telegram->syncDefaultMiniAppMenuButton($user);

        $this->telegram->sendMessage(
            $chatId,
            TelegramCopy::for($user)->get('start.keyboard_hint'),
            ['reply_markup' => $this->telegram->mainKeyboard($user)],
        );
    }

    protected function sendHome(User $user, int|string $chatId): void
    {
        if ($this->settings->hasCustomTelegramStartMessage()) {
            $this->sendCustomStartMessage($user, $chatId);

            return;
        }

        $this->sendDefaultStartMessage($user, $chatId);
    }

    protected function sendDefaultStartMessage(User $user, int|string $chatId, bool $sendReplyKeyboard = true): void
    {
        $copy = TelegramCopy::for($user);
        $name = $copy->firstName($user);
        $hasCourses = $user->courses()->exists();

        if (! $hasCourses) {
            $this->telegram->sendMessage($chatId, $this->withReferralCta($copy->get('start.new', ['name' => $name]), $user), [
                'reply_markup' => [
                    'inline_keyboard' => [[
                        [
                            'text' => $copy->get('start.new_button'),
                            'web_app' => ['url' => $this->telegram->miniAppUrl('tg.profile.edit')],
                            'style' => TelegramButtonStyle::Primary->value,
                        ],
                    ]],
                ],
            ]);

            if ($sendReplyKeyboard) {
                $this->sendReplyKeyboard($user, $chatId);
            }

            return;
        }

        $this->telegram->sendMessage($chatId, $this->withReferralCta($copy->get('start.returning', ['name' => $name]), $user), [
            'reply_markup' => [
                'inline_keyboard' => [[
                    [
                        'text' => $copy->get('start.returning_button'),
                        'web_app' => ['url' => $this->telegram->miniAppUrl('tg.browse')],
                        'style' => TelegramButtonStyle::Primary->value,
                    ],
                ]],
            ],
        ]);

        if ($sendReplyKeyboard) {
            $this->sendReplyKeyboard($user, $chatId);
        }
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $extraButtonRows
     */
    protected function sendCustomStartMessage(User $user, int|string $chatId, array $extraButtonRows = [], bool $sendReplyKeyboard = true): void
    {
        $copy = TelegramCopy::for($user);
        $caption = TelegramHtml::fromRichHtml($this->settings->telegramStartCaption());
        $caption = str_replace(
            ['{{first_name}}', '{{name}}'],
            [
                TelegramHtml::escape($copy->firstName($user)),
                TelegramHtml::escape($user->name),
            ],
            $caption,
        );
        $caption = $this->withReferralCta($caption, $user);

        $inlineKeyboard = app(BroadcastService::class)->inlineKeyboard($this->settings->telegramStartButtons());
        $rows = $inlineKeyboard['inline_keyboard'] ?? [];
        $rows = array_merge($rows, $extraButtonRows);
        $payload = [];

        if ($rows !== []) {
            $payload['reply_markup'] = ['inline_keyboard' => $rows];
        }

        $imagePath = $this->settings->telegramStartImage();

        if ($imagePath !== null) {
            $absolutePath = Storage::disk('public')->path($imagePath);

            if (! is_file($absolutePath)) {
                Log::warning('Telegram start image missing on disk.', ['path' => $imagePath]);
                if ($caption !== '') {
                    $this->telegram->sendMessage($chatId, $caption, $payload);
                }
            } else {
                $this->telegram->sendPhoto($chatId, $absolutePath, $caption, $payload);
            }
        } elseif ($caption !== '') {
            $this->telegram->sendMessage($chatId, $caption, $payload);
        } elseif ($rows !== []) {
            $this->telegram->sendMessage($chatId, '‎', $payload);
        }

        if ($sendReplyKeyboard && $user->onboarding_step === OnboardingStep::Complete) {
            $this->sendReplyKeyboard($user, $chatId);
        }
    }

    protected function referralCtaHtml(User $user): string
    {
        $copy = TelegramCopy::for($user);
        $url = $this->referrals->referralLink($user);

        return '<a href="'.TelegramHtml::escape($url).'">'.TelegramHtml::escape($copy->get('refer.start_cta')).'</a>';
    }

    protected function withReferralCta(string $text, User $user): string
    {
        $cta = $this->referralCtaHtml($user);
        $url = $this->referrals->referralLink($user);

        if (str_contains($text, '{{referral_cta}}')) {
            return str_replace('{{referral_cta}}', $cta, $text);
        }

        if ($text !== '' && str_contains($text, $url)) {
            return $text;
        }

        if ($text === '') {
            return $cta;
        }

        return rtrim($text)."\n\n".$cta;
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
                        'web_app' => ['url' => $this->telegram->miniAppUrl('tg.profile.edit')],
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

        $typesWithContent = LearningResource::query()
            ->published()
            ->where('course_id', $courseId)
            ->toBase()
            ->distinct()
            ->pluck('type')
            ->all();

        $rows = [];

        foreach (ResourceType::creatableCases() as $type) {
            if (! in_array($type->value, $typesWithContent, true)) {
                continue;
            }

            $rows[] = [[
                'text' => $type->label(),
                'callback_data' => "rtype:{$type->value}:{$courseId}",
                'style' => TelegramButtonStyle::Success->value,
            ]];
        }

        if ($rows === []) {
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

        $rows[] = [[
            'text' => $copy->get('hub.back'),
            'callback_data' => 'back:courses',
        ]];

        $this->telegram->replyOrEdit(
            $chatId,
            $copy->get('browse.course_hubs', ['course' => $course->name]),
            [
                'reply_markup' => $this->telegram->inlineKeyboard($rows),
            ],
            $messageId,
        );
    }

    protected function isNavigationStartPayload(?string $payload): bool
    {
        if (! filled($payload)) {
            return false;
        }

        return $payload === 'bait'
            || $payload === 'web'
            || str_starts_with($payload, 'resource_')
            || str_starts_with($payload, 'course_')
            || str_starts_with($payload, 'challenge_')
            || str_starts_with($payload, 'opp_');
    }

    protected function pendingStartCacheKey(User $user): string
    {
        return "telegram.pending_start.{$user->id}";
    }

    protected function resumeStartPayload(User $user, int|string $chatId, ?string $payload = null): bool
    {
        $payload ??= Cache::pull($this->pendingStartCacheKey($user));

        if (! filled($payload) || ! $this->isNavigationStartPayload($payload)) {
            return false;
        }

        Cache::forget($this->pendingStartCacheKey($user));

        if ($payload === 'bait' || $payload === 'web') {
            $course = $user->courses()->orderBy('courses.name')->first();

            if ($course !== null) {
                $this->showCourseHub($user, $chatId, $course->id);

                return true;
            }

            $this->showBrowse($user, $chatId);

            return true;
        }

        if (str_starts_with($payload, 'resource_')) {
            $resourceId = (int) Str::after($payload, 'resource_');

            if ($resourceId > 0) {
                $this->openResource($user, $chatId, $resourceId);

                return true;
            }
        }

        if (str_starts_with($payload, 'course_')) {
            $slug = Str::after($payload, 'course_');
            $course = Course::query()->where('slug', $slug)->first();

            if ($course !== null) {
                if (! $user->courses()->where('courses.id', $course->id)->exists()) {
                    $user->courses()->syncWithoutDetaching([$course->id]);
                }

                $this->showCourseHub($user, $chatId, $course->id);

                return true;
            }
        }

        if (str_starts_with($payload, 'challenge_')) {
            $challengeId = (int) Str::after($payload, 'challenge_');

            if ($challengeId > 0) {
                $this->showChallenge($user, $chatId, $challengeId);

                return true;
            }
        }

        $opportunityShare = app(TelegramDeepLink::class)->parseOpportunitySharePayload($payload);

        if ($opportunityShare !== null && $opportunityShare['opportunity_id'] > 0) {
            $this->showOpportunity($user, $chatId, $opportunityShare['opportunity_id']);

            return true;
        }

        return false;
    }

    protected function listResources(
        User $user,
        int|string $chatId,
        int $courseId,
        ?ResourceHub $hub = null,
        ?int $messageId = null,
    ): void {
        $copy = TelegramCopy::for($user);
        $course = Course::query()->find($courseId);
        $heading = $this->resourceListHeading($copy, $course?->name, $hub?->label());

        $query = LearningResource::query()
            ->published()
            ->where('course_id', $courseId)
            ->orderBy('title');

        if ($hub !== null) {
            $query->whereIn('type', $hub->typeValues());
        }

        $this->replyWithResourceList(
            $copy,
            $chatId,
            $query->get(),
            $hub !== null ? "course:{$courseId}" : 'back:courses',
            $messageId,
            $heading,
        );
    }

    protected function listResourcesByType(
        User $user,
        int|string $chatId,
        int $courseId,
        ResourceType $type,
        ?int $messageId = null,
    ): void {
        $copy = TelegramCopy::for($user);
        $course = Course::query()->find($courseId);
        $heading = $this->resourceListHeading($copy, $course?->name, $type->label());

        $resources = LearningResource::query()
            ->published()
            ->where('course_id', $courseId)
            ->where('type', $type)
            ->orderBy('title')
            ->get();

        $this->replyWithResourceList(
            $copy,
            $chatId,
            $resources,
            "course:{$courseId}",
            $messageId,
            $heading,
        );
    }

    protected function resourceListHeading(TelegramCopy $copy, ?string $courseName, ?string $typeLabel): string
    {
        if (filled($courseName) && filled($typeLabel)) {
            return $copy->get('hub.resources_for', [
                'course' => $courseName,
                'type' => $typeLabel,
            ]);
        }

        return $copy->get('hub.resources');
    }

    /**
     * @param  Collection<int, LearningResource>  $resources
     */
    protected function replyWithResourceList(
        TelegramCopy $copy,
        int|string $chatId,
        Collection $resources,
        string $backCallback,
        ?int $messageId = null,
        ?string $heading = null,
    ): void {
        if ($resources->isEmpty()) {
            $this->telegram->replyOrEdit($chatId, $copy->get('hub.no_resources'), [
                'reply_markup' => $this->telegram->inlineKeyboard([
                    [['text' => $copy->get('hub.back'), 'callback_data' => $backCallback]],
                ]),
            ], $messageId);

            return;
        }

        $rows = $resources->map(fn (LearningResource $resource) => [
            $this->resourceInlineButton($resource),
        ])->values()->all();

        $rows[] = [[
            'text' => $copy->get('hub.back'),
            'callback_data' => $backCallback,
        ]];

        $this->telegram->replyOrEdit($chatId, $heading ?? $copy->get('hub.resources'), [
            'reply_markup' => $this->telegram->inlineKeyboard($rows),
        ], $messageId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resourceInlineButton(LearningResource $resource, ?string $label = null): array
    {
        $showLock = $this->premium->isEnabled() && $resource->is_premium;
        $text = $label ?? (($showLock ? '🔒 ' : '').$resource->title);

        if ($resource->hasFiles()) {
            return [
                'text' => $text,
                'callback_data' => "open_resource:{$resource->id}",
                'style' => TelegramButtonStyle::Primary->value,
            ];
        }

        return [
            'text' => $text,
            'web_app' => [
                'url' => $this->telegram->miniAppUrl($resource->miniAppRouteName(), [
                    'resource' => $resource,
                ]),
            ],
            'style' => TelegramButtonStyle::Success->value,
        ];
    }

    protected function openResource(User $user, int|string $chatId, int $resourceId): void
    {
        $copy = TelegramCopy::for($user);
        $resource = LearningResource::query()->published()->with('course')->find($resourceId);

        if ($resource === null) {
            $this->telegram->sendMessage($chatId, $copy->get('resource.not_found'));

            return;
        }

        if (! $resource->hasFiles()) {
            $this->telegram->sendMessage($chatId, '<b>'.e($resource->title).'</b>', [
                'reply_markup' => $this->telegram->inlineKeyboard([
                    [$this->resourceInlineButton($resource)],
                ]),
            ]);

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
        $this->sendResourceFiles($user, $chatId, $resource);
    }

    /**
     * @return array{inline_keyboard: list<list<array<string, mixed>>>}
     */
    protected function resourceFileKeyboard(User $user, LearningResource $resource): array
    {
        $copy = TelegramCopy::for($user);
        $isSaved = $user->bookmarks()
            ->whereMorphedTo('bookmarkable', $resource)
            ->exists();

        $saveButton = [
            'text' => $isSaved ? $copy->get('saved.unsave') : $copy->get('resource.quick_save'),
            'callback_data' => 'save:resource:'.$resource->id,
        ];

        if ($isSaved) {
            $saveButton['style'] = TelegramButtonStyle::Success->value;
        }

        $deepLink = app(TelegramDeepLink::class)->forResource($resource->id);
        $shareText = $copy->get('resource.share_message', [
            'title' => $resource->title,
            'description' => filled($resource->description) ? (string) $resource->description : '',
            'link' => $deepLink,
        ]);
        $shareText = trim(preg_replace("/\n{3,}/", "\n\n", $shareText) ?? $shareText);
        $shareUrl = 'https://t.me/share/url?url='.rawurlencode($deepLink).'&text='.rawurlencode($shareText);

        return $this->telegram->inlineKeyboard([[
            $saveButton,
            [
                'text' => $copy->get('resource.share'),
                'url' => $shareUrl,
            ],
        ]]);
    }

    protected function sendResourceFiles(User $user, int|string $chatId, LearningResource $resource): void
    {
        $caption = $resource->title;
        $sent = false;
        $markup = [
            'reply_markup' => $this->resourceFileKeyboard($user, $resource),
        ];

        foreach ($resource->telegram_files ?? [] as $telegramFile) {
            if (! is_array($telegramFile)) {
                continue;
            }

            $fileId = $telegramFile['file_id'] ?? null;

            if (! is_string($fileId) || blank($fileId)) {
                continue;
            }

            $this->telegram->sendDocument(
                $chatId,
                $fileId,
                $sent ? '' : $caption,
                $markup,
            );
            $sent = true;
        }

        if ($sent) {
            return;
        }

        $disk = Storage::disk(config('filesystems.default'));

        foreach ($resource->files ?? [] as $path) {
            if (! is_string($path) || blank($path)) {
                continue;
            }

            if (! $disk->exists($path)) {
                Log::warning('Telegram resource file missing on disk.', [
                    'resource_id' => $resource->id,
                    'path' => $path,
                ]);

                continue;
            }

            $absolutePath = $disk->path($path);

            if (! is_file($absolutePath)) {
                Log::warning('Telegram resource file path is not a local file.', [
                    'resource_id' => $resource->id,
                    'path' => $path,
                ]);

                continue;
            }

            $this->telegram->sendDocument(
                $chatId,
                $absolutePath,
                $sent ? '' : $caption,
                $markup,
            );
            $sent = true;
        }

        if (! $sent) {
            $this->telegram->sendMessage(
                $chatId,
                '<b>'.e($resource->title).'</b>',
                $markup,
            );
        }
    }

    protected function tryHandleCustomCommand(User $user, int|string $chatId, string $text): bool
    {
        if (! str_starts_with($text, '/')) {
            return false;
        }

        $commandName = $this->parseSlashCommandName($text);

        if ($commandName === null || TelegramCommand::isReservedCommand($commandName)) {
            return false;
        }

        $command = TelegramCommand::query()
            ->active()
            ->where('command', $commandName)
            ->with(['fileAssets'])
            ->first();

        if ($command === null) {
            return false;
        }

        Context::add('telegram_command', $command->command);
        $this->sendCustomCommandResponse($user, $chatId, $command);

        return true;
    }

    protected function parseSlashCommandName(string $text): ?string
    {
        $firstToken = explode(' ', trim($text), 2)[0] ?? '';
        $withoutSlash = ltrim($firstToken, '/');

        if ($withoutSlash === '') {
            return null;
        }

        $withoutBotMention = explode('@', $withoutSlash, 2)[0] ?? '';
        $normalized = TelegramCommand::normalizeCommand($withoutBotMention);

        if ($normalized === '' || ! preg_match('/^[a-z0-9_]{1,32}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }

    protected function sendCustomCommandResponse(User $user, int|string $chatId, TelegramCommand $command): void
    {
        if (filled($command->message)) {
            $this->telegram->sendMessage($chatId, (string) $command->message);
        }

        foreach ($command->fileAssets as $asset) {
            if (blank($asset->file_id)) {
                continue;
            }

            $this->telegram->sendDocument($chatId, (string) $asset->file_id);
        }

        if ($command->learning_resource_id !== null) {
            $this->openResource($user, $chatId, (int) $command->learning_resource_id);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function tryHandleAdminDocument(array $message): bool
    {
        $document = $message['document'] ?? null;

        if (! is_array($document) || blank($document['file_id'] ?? null)) {
            return false;
        }

        $username = $message['from']['username'] ?? null;

        if (! $this->telegram->isFileVaultAdmin(is_string($username) ? $username : null)) {
            return false;
        }

        $fileId = (string) $document['file_id'];
        $fileUniqueId = filled($document['file_unique_id'] ?? null)
            ? (string) $document['file_unique_id']
            : $fileId;

        $asset = TelegramFileAsset::query()->updateOrCreate(
            ['file_unique_id' => $fileUniqueId],
            [
                'file_id' => $fileId,
                'file_name' => filled($document['file_name'] ?? null) ? (string) $document['file_name'] : null,
                'mime_type' => filled($document['mime_type'] ?? null) ? (string) $document['mime_type'] : null,
                'file_size' => isset($document['file_size']) ? (int) $document['file_size'] : null,
                'uploaded_by_username' => ltrim((string) $username, '@'),
            ],
        );

        $telegramId = (int) ($message['from']['id'] ?? 0);
        $chatId = $message['chat']['id'];
        $mediaGroupId = filled($message['media_group_id'] ?? null)
            ? (string) $message['media_group_id']
            : null;

        $buffer = $this->adminPublishBuffer($telegramId) ?? [
            'asset_ids' => [],
            'prompt_message_id' => null,
            'media_group_id' => null,
        ];

        $assetIds = array_values(array_unique(array_map(
            'intval',
            $buffer['asset_ids'] ?? [],
        )));

        if (! in_array($asset->id, $assetIds, true)) {
            $assetIds[] = $asset->id;
        }

        $buffer = [
            'asset_ids' => $assetIds,
            'prompt_message_id' => isset($buffer['prompt_message_id'])
                ? (int) $buffer['prompt_message_id']
                : null,
            'media_group_id' => $mediaGroupId ?? ($buffer['media_group_id'] ?? null),
        ];

        if (($buffer['prompt_message_id'] ?? 0) < 1) {
            $buffer['prompt_message_id'] = null;
        }

        $this->putAdminPublishBuffer($telegramId, $buffer);
        $this->refreshAdminPublishBufferPrompt($telegramId, $chatId);

        return true;
    }

    protected function refreshAdminPublishBufferPrompt(int $telegramId, int|string $chatId): void
    {
        $buffer = $this->adminPublishBuffer($telegramId);

        if ($buffer === null) {
            return;
        }

        $assetIds = array_values(array_unique(array_map(
            'intval',
            $buffer['asset_ids'] ?? [],
        )));

        if ($assetIds === []) {
            $this->clearAdminPublishBuffer($telegramId);

            return;
        }

        $promptMessageId = isset($buffer['prompt_message_id']) && (int) $buffer['prompt_message_id'] > 0
            ? (int) $buffer['prompt_message_id']
            : null;

        if (count($assetIds) === 1) {
            $result = $this->sendOrEditSingleFileStoredPrompt($chatId, $assetIds[0], $promptMessageId);
            $this->rememberAdminPublishPromptMessage($telegramId, $buffer, $result, $promptMessageId);

            return;
        }

        $assets = TelegramFileAsset::query()
            ->whereIn('id', $assetIds)
            ->orderBy('id')
            ->get();

        $lines = $assets
            ->map(function (TelegramFileAsset $asset): string {
                $name = filled($asset->file_name) ? $asset->file_name : ('#'.$asset->id);

                return '• '.e($name);
            })
            ->implode("\n");

        $text = '<b>Stored '.$assets->count().' files</b>'."\n"
            .$lines."\n\n"
            .'Bulk publish with the same stream, course, type, and premium setting?';

        $payload = [
            'reply_markup' => $this->telegram->inlineKeyboard([
                [[
                    'text' => 'Bulk publish',
                    'callback_data' => 'admin:publish:bulk',
                    'style' => TelegramButtonStyle::Success->value,
                ]],
                [[
                    'text' => 'Publish separately',
                    'callback_data' => 'admin:publish:separate',
                    'style' => TelegramButtonStyle::Primary->value,
                ]],
                [[
                    'text' => 'Done',
                    'callback_data' => 'admin:publish:done',
                ]],
            ]),
        ];

        $result = null;

        if ($promptMessageId !== null) {
            $result = $this->telegram->editMessageText($chatId, $promptMessageId, $text, $payload);
        }

        if ($result === null) {
            $result = $this->telegram->sendMessage($chatId, $text, $payload);
            $promptMessageId = null;
        }

        $this->rememberAdminPublishPromptMessage($telegramId, $buffer, $result, $promptMessageId);
    }

    /**
     * @param  array<string, mixed>  $buffer
     * @param  array<string, mixed>|null  $result
     */
    protected function rememberAdminPublishPromptMessage(
        int $telegramId,
        array $buffer,
        ?array $result,
        ?int $existingMessageId,
    ): void {
        $messageId = isset($result['message_id'])
            ? (int) $result['message_id']
            : $existingMessageId;

        if ($messageId === null || $messageId < 1) {
            return;
        }

        $buffer['prompt_message_id'] = $messageId;
        $this->putAdminPublishBuffer($telegramId, $buffer);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function sendOrEditSingleFileStoredPrompt(int|string $chatId, int $assetId, ?int $messageId = null): ?array
    {
        $asset = TelegramFileAsset::query()->find($assetId);

        if ($asset === null) {
            return null;
        }

        $displayName = filled($asset->file_name) ? $asset->file_name : 'document';

        $payload = [
            'reply_markup' => $this->telegram->inlineKeyboard([
                [[
                    'text' => 'Publish resource',
                    'callback_data' => 'admin:publish:'.$asset->id,
                    'style' => TelegramButtonStyle::Success->value,
                ]],
                [[
                    'text' => 'Done',
                    'callback_data' => 'admin:publish:done',
                ]],
            ]),
        ];

        $text = '<b>File stored</b>'."\n"
            .'Name: '.e($displayName)."\n"
            .'file_id: <code>'.e($asset->file_id).'</code>'."\n"
            .'Publish from Telegram, or attach this file_id in the admin panel.';

        if ($messageId !== null) {
            $result = $this->telegram->editMessageText($chatId, $messageId, $text, $payload);

            if ($result !== null) {
                return $result;
            }
        }

        return $this->telegram->sendMessage($chatId, $text, $payload);
    }

    protected function sendSingleFileStoredPrompt(int|string $chatId, int $assetId): void
    {
        $this->sendOrEditSingleFileStoredPrompt($chatId, $assetId);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function tryHandleAdminPublishTitle(array $message): bool
    {
        $username = $message['from']['username'] ?? null;

        if (! $this->telegram->isFileVaultAdmin(is_string($username) ? $username : null)) {
            return false;
        }

        $telegramId = (int) ($message['from']['id'] ?? 0);
        $session = $this->adminPublishSession($telegramId);

        if ($session === null || ($session['step'] ?? null) !== 'title') {
            return false;
        }

        $text = trim((string) ($message['text'] ?? ''));

        if ($text === '' || str_starts_with($text, '/')) {
            return false;
        }

        $session['title'] = mb_substr($text, 0, 200);
        $session['step'] = 'premium';
        $this->putAdminPublishSession($telegramId, $session);

        $this->askPublishPremium((int) $message['chat']['id']);

        return true;
    }

    /**
     * @param  array<string, mixed>  $from
     */
    protected function handleAdminPublishCallback(array $from, int|string $chatId, string $data, ?int $messageId): ?string
    {
        $username = $from['username'] ?? null;

        if (! $this->telegram->isFileVaultAdmin(is_string($username) ? $username : null)) {
            return 'Not allowed.';
        }

        $telegramId = (int) ($from['id'] ?? 0);

        if ($data === 'admin:publish:done') {
            $this->clearAdminPublish($telegramId);
            $this->telegram->replyOrEdit($chatId, 'Saved to the vault. You can publish later from Telegram or Filament.', [
                'reply_markup' => $this->telegram->inlineKeyboard([]),
            ], $messageId);

            return null;
        }

        if ($data === 'admin:publish:bulk') {
            return $this->startAdminBulkPublish($telegramId, $chatId, $messageId);
        }

        if ($data === 'admin:publish:separate') {
            return $this->startAdminSeparatePublish($telegramId, $chatId, $messageId);
        }

        if (str_starts_with($data, 'admin:publish:')) {
            $assetId = (int) Str::after($data, 'admin:publish:');

            if ($assetId < 1) {
                return 'That publish button is no longer valid.';
            }

            return $this->startAdminPublish($telegramId, $chatId, $assetId, $messageId);
        }

        if ($data === 'admin:pub:cancel') {
            $this->clearAdminPublish($telegramId);
            $this->telegram->replyOrEdit($chatId, 'Publish cancelled.', [
                'reply_markup' => $this->telegram->inlineKeyboard([]),
            ], $messageId);

            return null;
        }

        $session = $this->adminPublishSession($telegramId);

        if ($session === null) {
            return 'Publish session expired. Send the file again.';
        }

        if (str_starts_with($data, 'admin:pub:stream:')) {
            $streamId = (int) Str::after($data, 'admin:pub:stream:');
            $stream = Stream::query()->active()->find($streamId);

            if ($stream === null) {
                return 'That stream is unavailable.';
            }

            $session['stream_id'] = $stream->id;
            $session['course_id'] = null;
            $session['course_page'] = 1;
            $session['step'] = 'course';
            $this->putAdminPublishSession($telegramId, $session);
            $this->askPublishCourse($chatId, $session, $messageId);

            return null;
        }

        if (str_starts_with($data, 'admin:pub:page:')) {
            $page = max(1, (int) Str::after($data, 'admin:pub:page:'));
            $session['course_page'] = $page;
            $this->putAdminPublishSession($telegramId, $session);
            $this->askPublishCourse($chatId, $session, $messageId);

            return null;
        }

        if (str_starts_with($data, 'admin:pub:course:')) {
            $courseId = (int) Str::after($data, 'admin:pub:course:');
            $course = Course::query()
                ->active()
                ->where('stream_id', $session['stream_id'] ?? 0)
                ->find($courseId);

            if ($course === null) {
                return 'That course is unavailable.';
            }

            $session['course_id'] = $course->id;
            $session['step'] = 'type';
            $this->putAdminPublishSession($telegramId, $session);
            $this->askPublishType($chatId, $messageId);

            return null;
        }

        if (str_starts_with($data, 'admin:pub:type:')) {
            $typeValue = Str::after($data, 'admin:pub:type:');
            $type = ResourceType::tryFrom($typeValue);

            if ($type === null || ! in_array($type, $this->publishableResourceTypes(), true)) {
                return 'That type is unavailable.';
            }

            $session['type'] = $type->value;

            if ((bool) ($session['bulk'] ?? false)) {
                $session['step'] = 'premium';
                $this->putAdminPublishSession($telegramId, $session);
                $this->askPublishPremium($chatId, $messageId);

                return null;
            }

            $session['step'] = 'title';
            $this->putAdminPublishSession($telegramId, $session);
            $this->askPublishTitle($chatId, $session, $messageId);

            return null;
        }

        if ($data === 'admin:pub:title:filename') {
            $assetIds = $this->adminPublishAssetIds($session);
            $asset = TelegramFileAsset::query()->find($assetIds[0] ?? 0);

            if ($asset === null) {
                $this->clearAdminPublish($telegramId);

                return 'File missing from vault.';
            }

            $title = filled($asset->file_name)
                ? pathinfo((string) $asset->file_name, PATHINFO_FILENAME)
                : 'Untitled resource';

            $session['title'] = mb_substr($title !== '' ? $title : 'Untitled resource', 0, 200);
            $session['step'] = 'premium';
            $this->putAdminPublishSession($telegramId, $session);
            $this->askPublishPremium($chatId, $messageId);

            return null;
        }

        if (str_starts_with($data, 'admin:pub:premium:')) {
            $session['is_premium'] = Str::after($data, 'admin:pub:premium:') === '1';
            $session['step'] = 'confirm';
            $this->putAdminPublishSession($telegramId, $session);
            $this->askPublishConfirm($chatId, $session, $messageId);

            return null;
        }

        if ($data === 'admin:pub:confirm') {
            return $this->finalizeAdminPublish($telegramId, $chatId, $session, $messageId);
        }

        return 'That publish button is no longer valid.';
    }

    protected function startAdminPublish(int $telegramId, int|string $chatId, int $assetId, ?int $messageId): ?string
    {
        $asset = TelegramFileAsset::query()->find($assetId);

        if ($asset === null) {
            return 'File not found in vault.';
        }

        $this->clearAdminPublishBuffer($telegramId);

        $session = [
            'asset_id' => $asset->id,
            'asset_ids' => [$asset->id],
            'bulk' => false,
            'step' => 'stream',
            'stream_id' => null,
            'course_id' => null,
            'course_page' => 1,
            'type' => null,
            'title' => null,
            'is_premium' => false,
        ];

        $this->putAdminPublishSession($telegramId, $session);
        $this->askPublishStream($chatId, $messageId);

        return null;
    }

    protected function startAdminBulkPublish(int $telegramId, int|string $chatId, ?int $messageId): ?string
    {
        $buffer = $this->adminPublishBuffer($telegramId);
        $assetIds = array_values(array_unique(array_map(
            'intval',
            $buffer['asset_ids'] ?? [],
        )));

        if (count($assetIds) < 2) {
            return 'Bulk publish batch expired. Send the files again.';
        }

        $assets = TelegramFileAsset::query()->whereIn('id', $assetIds)->count();

        if ($assets !== count($assetIds)) {
            $this->clearAdminPublishBuffer($telegramId);

            return 'Some files are missing from the vault. Send them again.';
        }

        $this->clearAdminPublishBuffer($telegramId);

        $session = [
            'asset_id' => $assetIds[0],
            'asset_ids' => $assetIds,
            'bulk' => true,
            'step' => 'stream',
            'stream_id' => null,
            'course_id' => null,
            'course_page' => 1,
            'type' => null,
            'title' => null,
            'is_premium' => false,
        ];

        $this->putAdminPublishSession($telegramId, $session);
        $this->askPublishStream($chatId, $messageId);

        return null;
    }

    protected function startAdminSeparatePublish(int $telegramId, int|string $chatId, ?int $messageId): ?string
    {
        $buffer = $this->adminPublishBuffer($telegramId);
        $assetIds = array_values(array_unique(array_map(
            'intval',
            $buffer['asset_ids'] ?? [],
        )));

        if ($assetIds === []) {
            return 'Publish batch expired. Send the files again.';
        }

        $this->clearAdminPublishBuffer($telegramId);

        $this->telegram->replyOrEdit(
            $chatId,
            'Publish each file separately:',
            ['reply_markup' => $this->telegram->inlineKeyboard([])],
            $messageId,
        );

        foreach ($assetIds as $assetId) {
            $this->sendSingleFileStoredPrompt($chatId, $assetId);
        }

        return null;
    }

    protected function askPublishStream(int|string $chatId, ?int $messageId = null): void
    {
        $streams = Stream::query()->active()->orderBy('name')->get();

        if ($streams->isEmpty()) {
            $this->telegram->replyOrEdit($chatId, 'No streams are available.', [
                'reply_markup' => $this->telegram->inlineKeyboard([
                    [['text' => 'Cancel', 'callback_data' => 'admin:pub:cancel']],
                ]),
            ], $messageId);

            return;
        }

        $rows = $streams->map(fn (Stream $stream) => [[
            'text' => $stream->name,
            'callback_data' => 'admin:pub:stream:'.$stream->id,
            'style' => TelegramButtonStyle::Primary->value,
        ]])->values()->all();

        $rows[] = [['text' => 'Cancel', 'callback_data' => 'admin:pub:cancel']];

        $this->telegram->replyOrEdit($chatId, 'Pick a stream for this resource:', [
            'reply_markup' => $this->telegram->inlineKeyboard($rows),
        ], $messageId);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function askPublishCourse(int|string $chatId, array $session, ?int $messageId = null): void
    {
        $perPage = 8;
        $page = max(1, (int) ($session['course_page'] ?? 1));
        $streamId = (int) ($session['stream_id'] ?? 0);

        $query = Course::query()->active()->where('stream_id', $streamId)->orderBy('name');
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->telegram->replyOrEdit($chatId, 'No courses in that stream.', [
                'reply_markup' => $this->telegram->inlineKeyboard([
                    [['text' => 'Cancel', 'callback_data' => 'admin:pub:cancel']],
                ]),
            ], $messageId);

            return;
        }

        $courses = $query->forPage($page, $perPage)->get();
        $rows = $courses->map(fn (Course $course) => [[
            'text' => $course->name,
            'callback_data' => 'admin:pub:course:'.$course->id,
            'style' => TelegramButtonStyle::Primary->value,
        ]])->values()->all();

        $nav = [];
        if ($page > 1) {
            $nav[] = ['text' => '‹ Prev', 'callback_data' => 'admin:pub:page:'.($page - 1)];
        }
        if ($page * $perPage < $total) {
            $nav[] = ['text' => 'Next ›', 'callback_data' => 'admin:pub:page:'.($page + 1)];
        }
        if ($nav !== []) {
            $rows[] = $nav;
        }

        $rows[] = [['text' => 'Cancel', 'callback_data' => 'admin:pub:cancel']];

        $this->telegram->replyOrEdit($chatId, 'Pick a course:', [
            'reply_markup' => $this->telegram->inlineKeyboard($rows),
        ], $messageId);
    }

    protected function askPublishType(int|string $chatId, ?int $messageId = null): void
    {
        $rows = collect($this->publishableResourceTypes())
            ->map(fn (ResourceType $type) => [[
                'text' => $type->label(),
                'callback_data' => 'admin:pub:type:'.$type->value,
                'style' => TelegramButtonStyle::Primary->value,
            ]])
            ->values()
            ->all();

        $rows[] = [['text' => 'Cancel', 'callback_data' => 'admin:pub:cancel']];

        $this->telegram->replyOrEdit($chatId, 'Pick a resource type:', [
            'reply_markup' => $this->telegram->inlineKeyboard($rows),
        ], $messageId);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function askPublishTitle(int|string $chatId, array $session, ?int $messageId = null): void
    {
        $assetIds = $this->adminPublishAssetIds($session);
        $asset = TelegramFileAsset::query()->find($assetIds[0] ?? 0);
        $filename = filled($asset?->file_name)
            ? pathinfo((string) $asset->file_name, PATHINFO_FILENAME)
            : 'Untitled resource';

        $this->telegram->replyOrEdit(
            $chatId,
            'Send a title as a text message, or use the filename:'."\n"
            .'<code>'.e($filename).'</code>',
            [
                'reply_markup' => $this->telegram->inlineKeyboard([
                    [[
                        'text' => 'Use filename',
                        'callback_data' => 'admin:pub:title:filename',
                        'style' => TelegramButtonStyle::Success->value,
                    ]],
                    [['text' => 'Cancel', 'callback_data' => 'admin:pub:cancel']],
                ]),
            ],
            $messageId,
        );
    }

    protected function askPublishPremium(int|string $chatId, ?int $messageId = null): void
    {
        $this->telegram->replyOrEdit($chatId, 'Is this a premium resource?', [
            'reply_markup' => $this->telegram->inlineKeyboard([
                [
                    ['text' => 'No', 'callback_data' => 'admin:pub:premium:0'],
                    ['text' => 'Yes', 'callback_data' => 'admin:pub:premium:1', 'style' => TelegramButtonStyle::Primary->value],
                ],
                [['text' => 'Cancel', 'callback_data' => 'admin:pub:cancel']],
            ]),
        ], $messageId);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function askPublishConfirm(int|string $chatId, array $session, ?int $messageId = null): void
    {
        $course = Course::query()->find($session['course_id'] ?? 0);
        $stream = Stream::query()->find($session['stream_id'] ?? 0);
        $type = ResourceType::tryFrom((string) ($session['type'] ?? ''));
        $assetIds = $this->adminPublishAssetIds($session);
        $assets = TelegramFileAsset::query()
            ->whereIn('id', $assetIds)
            ->orderBy('id')
            ->get();
        $isBulk = (bool) ($session['bulk'] ?? false) && $assets->count() > 1;

        if ($isBulk) {
            $fileLines = $assets
                ->values()
                ->map(function (TelegramFileAsset $asset, int $index): string {
                    $name = filled($asset->file_name)
                        ? pathinfo((string) $asset->file_name, PATHINFO_FILENAME)
                        : ('Document '.($index + 1));

                    return '• '.e($name !== '' ? $name : ('Document '.($index + 1)));
                })
                ->implode("\n");

            $summary = '<b>Publish '.$assets->count().' resources?</b>'."\n"
                .'Type: '.e($type?->label() ?? '-')."\n"
                .'Stream: '.e($stream?->name ?? '-')."\n"
                .'Course: '.e($course?->name ?? '-')."\n"
                .'Premium: '.(($session['is_premium'] ?? false) ? 'Yes' : 'No')."\n"
                .'Titles (from filenames):'."\n"
                .$fileLines;
        } else {
            $asset = $assets->first();

            $summary = '<b>Publish resource?</b>'."\n"
                .'Title: '.e((string) ($session['title'] ?? ''))."\n"
                .'Type: '.e($type?->label() ?? '-')."\n"
                .'Stream: '.e($stream?->name ?? '-')."\n"
                .'Course: '.e($course?->name ?? '-')."\n"
                .'Premium: '.(($session['is_premium'] ?? false) ? 'Yes' : 'No')."\n"
                .'File: '.e($asset?->file_name ?? $asset?->file_id ?? '-');
        }

        $this->telegram->replyOrEdit($chatId, $summary, [
            'reply_markup' => $this->telegram->inlineKeyboard([
                [[
                    'text' => $isBulk ? 'Publish all' : 'Publish',
                    'callback_data' => 'admin:pub:confirm',
                    'style' => TelegramButtonStyle::Success->value,
                ]],
                [['text' => 'Cancel', 'callback_data' => 'admin:pub:cancel']],
            ]),
        ], $messageId);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function finalizeAdminPublish(int $telegramId, int|string $chatId, array $session, ?int $messageId): ?string
    {
        $assetIds = $this->adminPublishAssetIds($session);
        $course = Course::query()->active()->find($session['course_id'] ?? 0);
        $type = ResourceType::tryFrom((string) ($session['type'] ?? ''));
        $isBulk = (bool) ($session['bulk'] ?? false);
        $title = trim((string) ($session['title'] ?? ''));

        if ($assetIds === [] || $course === null || $type === null || (! $isBulk && $title === '')) {
            $this->clearAdminPublish($telegramId);

            return 'Publish data incomplete. Send the file again.';
        }

        $assets = TelegramFileAsset::query()
            ->whereIn('id', $assetIds)
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        if ($assets->count() !== count($assetIds)) {
            $this->clearAdminPublish($telegramId);

            return 'Publish data incomplete. Send the file again.';
        }

        $created = [];
        $isPremium = (bool) ($session['is_premium'] ?? false);

        foreach ($assetIds as $index => $assetId) {
            /** @var TelegramFileAsset $asset */
            $asset = $assets->get($assetId);

            if ($isBulk) {
                $stem = filled($asset->file_name)
                    ? pathinfo((string) $asset->file_name, PATHINFO_FILENAME)
                    : '';
                $resourceTitle = $stem !== '' ? $stem : ('Document '.($index + 1));
            } else {
                $resourceTitle = $title;
            }

            $resourceTitle = mb_substr($resourceTitle, 0, 200);
            $telegramFiles = LearningResourceFiles::normalizeTelegramFileIds([$asset->file_id]);

            $created[] = LearningResource::query()->create([
                'title' => $resourceTitle,
                'slug' => $this->uniqueLearningSlug($resourceTitle),
                'type' => $type,
                'stream_id' => $course->stream_id,
                'course_id' => $course->id,
                'is_premium' => $isPremium,
                'is_published' => true,
                'telegram_files' => $telegramFiles,
                'files' => null,
            ]);
        }

        $this->clearAdminPublish($telegramId);

        if (count($created) === 1) {
            $resource = $created[0];

            $this->telegram->replyOrEdit(
                $chatId,
                '<b>Published</b>'."\n"
                .'Title: '.e($resource->title)."\n"
                .'Type: '.e($type->label())."\n"
                .'Course: '.e($course->name)."\n"
                .'ID: '.$resource->id,
                ['reply_markup' => $this->telegram->inlineKeyboard([])],
                $messageId,
            );

            return null;
        }

        $lines = collect($created)
            ->map(fn (LearningResource $resource): string => '• '.e($resource->title).' (ID '.$resource->id.')')
            ->implode("\n");

        $this->telegram->replyOrEdit(
            $chatId,
            '<b>Published '.count($created).' resources</b>'."\n"
            .'Type: '.e($type->label())."\n"
            .'Course: '.e($course->name)."\n"
            .'Premium: '.($isPremium ? 'Yes' : 'No')."\n"
            .$lines,
            ['reply_markup' => $this->telegram->inlineKeyboard([])],
            $messageId,
        );

        return null;
    }

    /**
     * @param  array<string, mixed>  $session
     * @return list<int>
     */
    protected function adminPublishAssetIds(array $session): array
    {
        if (isset($session['asset_ids']) && is_array($session['asset_ids'])) {
            return array_values(array_unique(array_map('intval', $session['asset_ids'])));
        }

        $assetId = (int) ($session['asset_id'] ?? 0);

        return $assetId > 0 ? [$assetId] : [];
    }

    /**
     * @return list<ResourceType>
     */
    protected function publishableResourceTypes(): array
    {
        return [
            ResourceType::Notes,
            ResourceType::Module,
            ResourceType::Slides,
            ResourceType::Worksheet,
            ResourceType::Assignment,
            ResourceType::MidExam,
            ResourceType::FinalExam,
            ResourceType::PracticeExams,
            ResourceType::ReferenceBooks,
        ];
    }

    protected function uniqueLearningSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'resource';
        $slug = $base;
        $suffix = 1;

        while (LearningResource::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    protected function adminPublishCacheKey(int $telegramId): string
    {
        return 'telegram.publish.'.$telegramId;
    }

    protected function adminPublishBufferCacheKey(int $telegramId): string
    {
        return 'telegram.publish.buffer.'.$telegramId;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function adminPublishSession(int $telegramId): ?array
    {
        $session = Cache::get($this->adminPublishCacheKey($telegramId));

        return is_array($session) ? $session : null;
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function putAdminPublishSession(int $telegramId, array $session): void
    {
        Cache::put($this->adminPublishCacheKey($telegramId), $session, now()->addHour());
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function adminPublishBuffer(int $telegramId): ?array
    {
        $buffer = Cache::get($this->adminPublishBufferCacheKey($telegramId));

        return is_array($buffer) ? $buffer : null;
    }

    /**
     * @param  array<string, mixed>  $buffer
     */
    protected function putAdminPublishBuffer(int $telegramId, array $buffer): void
    {
        Cache::put($this->adminPublishBufferCacheKey($telegramId), $buffer, now()->addMinutes(10));
    }

    protected function clearAdminPublishBuffer(int $telegramId): void
    {
        Cache::forget($this->adminPublishBufferCacheKey($telegramId));
    }

    protected function clearAdminPublish(int $telegramId): void
    {
        Cache::forget($this->adminPublishCacheKey($telegramId));
        $this->clearAdminPublishBuffer($telegramId);
    }

    protected function showPremium(User $user, int|string $chatId): void
    {
        if (! $this->premium->isEnabled()) {
            return;
        }

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

    protected function showResourceLibrary(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $copy = TelegramCopy::for($user);
        $courseIds = $user->courses()->pluck('courses.id');

        if ($courseIds->isEmpty()) {
            $this->telegram->replyOrEdit($chatId, $copy->get('browse.empty'), [
                'reply_markup' => $this->telegram->inlineKeyboard([[
                    [
                        'text' => $copy->get('browse.empty_button'),
                        'web_app' => ['url' => $this->telegram->miniAppUrl('tg.profile.edit')],
                        'style' => TelegramButtonStyle::Primary->value,
                    ],
                ]]),
            ], $messageId);

            return;
        }

        $rows = [];

        foreach (ResourceType::creatableCases() as $type) {
            $count = LearningResource::query()
                ->published()
                ->whereIn('course_id', $courseIds)
                ->where('type', $type->value)
                ->count();

            if ($count === 0) {
                continue;
            }

            $rows[] = [[
                'text' => $type->label().' ('.$count.')',
                'callback_data' => 'type_lib:'.$type->value,
                'style' => TelegramButtonStyle::Primary->value,
            ]];
        }

        if ($rows === []) {
            $this->telegram->replyOrEdit($chatId, $copy->get('library.empty'), [
                'reply_markup' => $this->telegram->inlineKeyboard([]),
            ], $messageId);

            return;
        }

        $this->telegram->replyOrEdit($chatId, $copy->get('library.title'), [
            'reply_markup' => $this->telegram->inlineKeyboard($rows),
        ], $messageId);
    }

    protected function handleLibraryTypeCallback(User $user, int|string $chatId, string $data, ?int $messageId = null): ?string
    {
        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return TelegramCopy::for($user)->get('menu.finish_onboarding');
        }

        $typeValue = str_starts_with($data, 'type_lib:')
            ? Str::after($data, 'type_lib:')
            : Str::after($data, 'hub_lib:');

        $type = ResourceType::tryFrom($typeValue);

        if ($type === null) {
            return TelegramCopy::for($user)->get('menu.invalid_button');
        }

        $this->listLibraryResources($user, $chatId, $type, $messageId);

        return null;
    }

    protected function listLibraryResources(
        User $user,
        int|string $chatId,
        ResourceType $type,
        ?int $messageId = null,
    ): void {
        $copy = TelegramCopy::for($user);
        $courseIds = $user->courses()->pluck('courses.id');

        $resources = LearningResource::query()
            ->published()
            ->with('course')
            ->whereIn('course_id', $courseIds)
            ->where('type', $type->value)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->limit(40)
            ->get();

        if ($resources->isEmpty()) {
            $this->telegram->replyOrEdit(
                $chatId,
                $copy->get('library.hub_empty', ['hub' => strtolower($type->label())]),
                [
                    'reply_markup' => $this->telegram->inlineKeyboard([[
                        ['text' => $copy->get('library.back'), 'callback_data' => 'back:library'],
                    ]]),
                ],
                $messageId,
            );

            return;
        }

        $rows = $resources->map(fn (LearningResource $resource) => [
            $this->resourceInlineButton($resource),
        ])->values()->all();

        $rows[] = [[
            'text' => $copy->get('library.back'),
            'callback_data' => 'back:library',
        ]];

        $this->telegram->replyOrEdit($chatId, $type->label(), [
            'reply_markup' => $this->telegram->inlineKeyboard($rows),
        ], $messageId);
    }

    /**
     * @return Collection<int, Bookmark>
     */
    protected function publishedSavedBookmarks(User $user, string $tab): Collection
    {
        $opportunityType = OpportunityType::fromSavedTabKey($tab);

        return $user->bookmarks()
            ->with(['bookmarkable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    LearningResource::class => ['course'],
                ]);
            }])
            ->latest('id')
            ->get()
            ->filter(function ($bookmark) use ($tab, $opportunityType): bool {
                $item = $bookmark->bookmarkable;

                if ($tab === 'resources') {
                    return $item instanceof LearningResource && $item->is_published;
                }

                return $opportunityType !== null
                    && $item instanceof Opportunity
                    && $item->is_published
                    && $item->type === $opportunityType;
            })
            ->values();
    }

    protected function resolveDefaultSavedTab(User $user): string
    {
        foreach ($this->telegram->savedTabKeys() as $tab) {
            if ($this->publishedSavedBookmarks($user, $tab)->isNotEmpty()) {
                return $tab;
            }
        }

        return 'resources';
    }

    protected function showSaved(
        User $user,
        int|string $chatId,
        ?int $messageId = null,
        ?string $tab = null,
        int $page = 0,
    ): void {
        $copy = TelegramCopy::for($user);
        $perPage = 5;
        $tabKeys = $this->telegram->savedTabKeys();
        $openingFromMenu = $messageId === null && $tab === null;

        if ($tab === null || ! in_array($tab, $tabKeys, true)) {
            $tab = $this->resolveDefaultSavedTab($user);
        }

        if ($openingFromMenu) {
            $this->telegram->sendMessage($chatId, $copy->get('saved.choose'), [
                'reply_markup' => $this->telegram->savedKeyboard($user),
            ]);
        }

        $allEmpty = collect($tabKeys)->every(
            fn (string $key): bool => $this->publishedSavedBookmarks($user, $key)->isEmpty()
        );

        if ($allEmpty) {
            $this->telegram->replyOrEdit($chatId, $copy->get('saved.empty'), [
                'reply_markup' => $this->telegram->inlineKeyboard([[
                    [
                        'text' => $copy->get('keyboard.courses'),
                        'callback_data' => 'browse',
                        'style' => TelegramButtonStyle::Primary->value,
                    ],
                ]]),
            ], $messageId);

            return;
        }

        $bookmarks = $this->publishedSavedBookmarks($user, $tab);

        if ($bookmarks->isEmpty()) {
            $this->telegram->replyOrEdit($chatId, $copy->get('saved.empty_tab', [
                'tab' => $this->telegram->savedTabKeyboardLabel($copy, $tab),
            ]), [], $messageId);

            return;
        }

        $totalPages = (int) max(1, (int) ceil($bookmarks->count() / $perPage));
        $page = max(0, min($page, $totalPages - 1));

        $rows = [];

        foreach ($bookmarks->slice($page * $perPage, $perPage) as $bookmark) {
            $item = $bookmark->bookmarkable;

            if ($item instanceof LearningResource) {
                $rows[] = [$this->resourceInlineButton($item)];
            } elseif ($item instanceof Opportunity) {
                $rows[] = [$this->opportunityInlineButton($item)];
            }
        }

        if ($totalPages > 1) {
            $nav = [];

            if ($page > 0) {
                $nav[] = [
                    'text' => $copy->get('saved.back'),
                    'callback_data' => "saved:page:{$tab}:".($page - 1),
                ];
            }

            if ($page < $totalPages - 1) {
                $nav[] = [
                    'text' => $copy->get('saved.next'),
                    'callback_data' => "saved:page:{$tab}:".($page + 1),
                    'style' => TelegramButtonStyle::Primary->value,
                ];
            }

            $rows[] = $nav;
        }

        $this->telegram->replyOrEdit($chatId, $copy->get('saved.page_title', [
            'tab' => $this->telegram->savedTabKeyboardLabel($copy, $tab),
            'page' => $page + 1,
            'pages' => $totalPages,
        ]), [
            'reply_markup' => $this->telegram->inlineKeyboard($rows),
        ], $messageId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function opportunityInlineButton(Opportunity $opportunity): array
    {
        return [
            'text' => $opportunity->title,
            'callback_data' => 'open_opp:'.$opportunity->id,
            'style' => TelegramButtonStyle::Primary->value,
        ];
    }

    protected function showOpportunities(
        User $user,
        int|string $chatId,
        OpportunityType $type,
        ?int $messageId = null,
        int $page = 0,
    ): void {
        $copy = TelegramCopy::for($user);
        $perPage = 5;
        $typeLabel = $copy->get('opportunities.type_'.$type->copyKey());

        $opportunities = Opportunity::query()
            ->published()
            ->ofType($type)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        if ($opportunities->isEmpty()) {
            $this->telegram->replyOrEdit($chatId, $copy->get('opportunities.empty', [
                'type' => $typeLabel,
            ]), [
                'reply_markup' => $this->telegram->mainKeyboard($user),
            ], $messageId);

            return;
        }

        $totalPages = (int) max(1, (int) ceil($opportunities->count() / $perPage));
        $page = max(0, min($page, $totalPages - 1));

        $rows = $opportunities
            ->slice($page * $perPage, $perPage)
            ->map(fn (Opportunity $opportunity): array => [
                $this->opportunityInlineButton($opportunity),
            ])
            ->values()
            ->all();

        if ($totalPages > 1) {
            $nav = [];

            if ($page > 0) {
                $nav[] = [
                    'text' => $copy->get('saved.back'),
                    'callback_data' => 'opp:page:'.$type->value.':'.($page - 1),
                ];
            }

            if ($page < $totalPages - 1) {
                $nav[] = [
                    'text' => $copy->get('saved.next'),
                    'callback_data' => 'opp:page:'.$type->value.':'.($page + 1),
                    'style' => TelegramButtonStyle::Primary->value,
                ];
            }

            $rows[] = $nav;
        }

        $this->telegram->replyOrEdit($chatId, $copy->get('opportunities.list_title', [
            'type' => $typeLabel,
            'page' => $page + 1,
            'pages' => $totalPages,
        ]), [
            'reply_markup' => $this->telegram->inlineKeyboard($rows),
        ], $messageId);
    }

    protected function showOpportunity(
        User $user,
        int|string $chatId,
        int $opportunityId,
        ?int $messageId = null,
    ): void {
        $copy = TelegramCopy::for($user);

        $opportunity = Opportunity::query()->published()->find($opportunityId);

        if ($opportunity === null) {
            $this->telegram->replyOrEdit($chatId, $copy->get('opportunities.not_found'), [], $messageId);

            return;
        }

        $deadline = $opportunity->deadline !== null
            ? $copy->get('opportunities.deadline', ['date' => $opportunity->deadline->toFormattedDateString()])
            : $copy->get('opportunities.deadline_none');

        $description = filled($opportunity->description)
            ? TelegramHtml::escape($opportunity->description)
            : '';

        $body = '<b>'.TelegramHtml::escape($opportunity->title)."</b>\n\n"
            .($description !== '' ? $description."\n\n" : '')
            .($opportunity->hasVerifiedPartner()
                ? $copy->get('opportunities.verified_partner', ['partner' => TelegramHtml::escape($opportunity->partner_name)])."\n\n"
                : '')
            .$deadline;

        $this->telegram->replyOrEdit($chatId, $body, [
            'reply_markup' => $this->opportunityDetailKeyboard($user, $opportunity),
        ], $messageId);
    }

    /**
     * @return array{inline_keyboard: list<list<array<string, mixed>>>}
     */
    protected function opportunityDetailKeyboard(User $user, Opportunity $opportunity): array
    {
        $copy = TelegramCopy::for($user);
        $isComplete = $user->onboarding_step === OnboardingStep::Complete;
        $isSaved = $user->bookmarks()
            ->whereMorphedTo('bookmarkable', $opportunity)
            ->exists();

        $saveButton = [
            'text' => $isSaved ? $copy->get('saved.unsave') : $copy->get('resource.quick_save'),
            'callback_data' => 'save:opp:'.$opportunity->id,
        ];

        if ($isSaved) {
            $saveButton['style'] = TelegramButtonStyle::Success->value;
        }

        $deepLink = app(TelegramDeepLink::class)->forOpportunity($opportunity->id, $user);
        $shareText = $copy->get('opportunities.share_message', [
            'title' => $opportunity->title,
            'link' => $deepLink,
        ]);
        $shareText = trim(preg_replace("/\n{3,}/", "\n\n", $shareText) ?? $shareText);
        $shareUrl = 'https://t.me/share/url?url='.rawurlencode($deepLink).'&text='.rawurlencode($shareText);

        $shareButton = [
            'text' => $copy->get('opportunities.share'),
            'url' => $shareUrl,
        ];

        $rows = [];

        if (filled($opportunity->url)) {
            if ($isComplete) {
                $rows[] = [[
                    'text' => $copy->get('opportunities.open_url'),
                    'url' => $opportunity->url,
                    'style' => TelegramButtonStyle::Primary->value,
                ]];
            } else {
                $rows[] = [[
                    'text' => $copy->get('opportunities.open_url'),
                    'callback_data' => 'opp:apply:'.$opportunity->id,
                    'style' => TelegramButtonStyle::Primary->value,
                ]];
            }
        }

        if ($opportunity->hasGuidanceAvailable()) {
            $rows[] = [[
                'text' => $copy->get('opportunities.request_guidance'),
                'callback_data' => 'opp:guidance:'.$opportunity->id,
                'style' => TelegramButtonStyle::Success->value,
            ]];
        }

        $rows[] = [$saveButton, $shareButton];
        $rows[] = [[
            'text' => $copy->get('opportunities.back'),
            'callback_data' => 'opp:page:'.$opportunity->type->value.':0',
        ]];

        return $this->telegram->inlineKeyboard($rows);
    }

    protected function handleSaveOpportunity(
        User $user,
        int|string $chatId,
        int $opportunityId,
        ?int $messageId = null,
    ): ?string {
        $copy = TelegramCopy::for($user);

        if ($user->onboarding_step !== OnboardingStep::Complete) {
            $this->promptCurrentOnboardingStep($user, $chatId);

            return $copy->get('menu.finish_onboarding');
        }

        $opportunity = Opportunity::query()->published()->find($opportunityId);

        if ($opportunity === null) {
            return $copy->get('opportunities.not_found');
        }

        $existing = $user->bookmarks()
            ->whereMorphedTo('bookmarkable', $opportunity)
            ->first();

        if ($existing !== null) {
            $existing->delete();
            $status = $copy->get('saved.removed_status');
        } else {
            $user->bookmarks()->create([
                'bookmarkable_type' => $opportunity->getMorphClass(),
                'bookmarkable_id' => $opportunity->id,
            ]);
            $status = $copy->get('saved.saved_status');
        }

        if ($messageId !== null) {
            $this->telegram->editMessageReplyMarkup(
                $chatId,
                $messageId,
                $this->opportunityDetailKeyboard($user, $opportunity),
            );
        }

        return $status;
    }

    protected function handleOpportunityApply(
        User $user,
        int|string $chatId,
        int $opportunityId,
    ): ?string {
        $copy = TelegramCopy::for($user);

        if ($user->onboarding_step !== OnboardingStep::Complete) {
            $this->promptCurrentOnboardingStep($user, $chatId);

            return $copy->get('menu.finish_onboarding');
        }

        $opportunity = Opportunity::query()->published()->find($opportunityId);

        if ($opportunity === null || blank($opportunity->url)) {
            return $copy->get('opportunities.not_found');
        }

        $this->telegram->sendMessage($chatId, $copy->get('opportunities.open_url'), [
            'reply_markup' => $this->telegram->inlineKeyboard([[
                [
                    'text' => $copy->get('opportunities.open_url'),
                    'url' => $opportunity->url,
                    'style' => TelegramButtonStyle::Primary->value,
                ],
            ]]),
        ]);

        return null;
    }

    protected function handleOpportunityGuidanceRequest(
        User $user,
        int|string $chatId,
        int $opportunityId,
    ): ?string {
        $copy = TelegramCopy::for($user);

        if ($user->onboarding_step !== OnboardingStep::Complete) {
            $this->promptCurrentOnboardingStep($user, $chatId);

            return $copy->get('menu.finish_onboarding');
        }

        $opportunity = Opportunity::query()->published()->find($opportunityId);

        if ($opportunity === null || ! $opportunity->hasGuidanceAvailable()) {
            return $copy->get('opportunities.not_found');
        }

        $this->opportunityGuidance->requestGuidance($user, $opportunity);

        return $copy->get('opportunities.guidance_sent');
    }

    protected function showReferrals(User $user, int|string $chatId): void
    {
        $copy = TelegramCopy::for($user);
        $stats = $copy->get('refer.stats', [
            'count' => $this->referrals->qualifiedCount($user),
            'required' => $this->settings->requiredReferrals(),
            'points' => $user->referral_points,
        ]);

        $this->telegram->sendMessage($chatId, $stats."\n\n".$copy->get('refer.forward_hint'), [
            'reply_markup' => $this->telegram->inlineKeyboard([[
                [
                    'text' => $copy->get('challenges.list_button'),
                    'callback_data' => 'challenges',
                    'style' => TelegramButtonStyle::Primary->value,
                ],
            ]]),
        ]);

        if ($this->settings->hasCustomTelegramStartMessage()) {
            $this->sendCustomStartMessage($user, $chatId, sendReplyKeyboard: false);

            return;
        }

        $this->sendDefaultStartMessage($user, $chatId, sendReplyKeyboard: false);
    }

    protected function showChallenges(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $copy = TelegramCopy::for($user);

        $challenges = Challenge::query()
            ->listed()
            ->orderBy('cost_points')
            ->orderBy('id')
            ->limit(10)
            ->get();

        if ($challenges->isEmpty()) {
            $this->telegram->replyOrEdit($chatId, $copy->get('challenges.empty', [
                'points' => $user->referral_points,
            ]), [], $messageId);

            return;
        }

        $rows = $challenges->map(fn (Challenge $challenge): array => [[
            'text' => $copy->get('challenges.item_button', [
                'title' => $challenge->title,
                'cost' => $challenge->cost_points,
            ]),
            'callback_data' => 'challenge:'.$challenge->id,
        ]])->values()->all();

        $this->telegram->replyOrEdit($chatId, $copy->get('challenges.list', [
            'points' => $user->referral_points,
        ]), [
            'reply_markup' => $this->telegram->inlineKeyboard($rows),
        ], $messageId);
    }

    protected function showChallenge(User $user, int|string $chatId, int $challengeId, ?int $messageId = null): void
    {
        $copy = TelegramCopy::for($user);

        $challenge = Challenge::query()->find($challengeId);

        if (
            $challenge === null
            || $challenge->status !== ChallengeStatus::Active
            || ! $challenge->hasAvailableSlots()
        ) {
            $this->telegram->replyOrEdit($chatId, $copy->get('challenges.not_found'), [
                'reply_markup' => $this->telegram->inlineKeyboard([[
                    ['text' => $copy->get('challenges.back'), 'callback_data' => 'challenges'],
                ]]),
            ], $messageId);

            return;
        }

        $completed = $challenge->completions()->where('user_id', $user->id)->exists();
        $description = filled($challenge->description)
            ? TelegramHtml::escape($challenge->description)
            : '';

        $replacements = [
            'title' => TelegramHtml::escape($challenge->title),
            'description' => $description,
            'cost' => $challenge->cost_points,
            'points' => $user->referral_points,
            'status' => $completed
                ? $copy->get('challenges.status_completed')
                : $copy->get('challenges.status_progress', [
                    'points' => $user->referral_points,
                    'cost' => $challenge->cost_points,
                ]),
        ];

        if ($completed) {
            $replacements['reward'] = filled($challenge->reward_message)
                ? TelegramHtml::escape($challenge->reward_message)
                : TelegramHtml::escape($challenge->title);

            $body = $copy->get('challenges.detail_completed', $replacements);
        } else {
            $body = $copy->get('challenges.detail', $replacements);
        }

        $this->telegram->replyOrEdit($chatId, $body, [
            'reply_markup' => $this->telegram->inlineKeyboard([[
                ['text' => $copy->get('challenges.back'), 'callback_data' => 'challenges'],
            ]]),
        ], $messageId);

        if (! $completed) {
            $this->challenges->tryAutoRedeem($user->fresh());
        }
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

        if ($user->isFreshman()) {
            $courses = $user->courses->pluck('name')->implode(', ') ?: $copy->get('profile.none');
            $body = $copy->get('profile.body', [
                'name' => $user->name,
                'stream' => $user->stream?->name ?? '-',
                'university' => $user->university?->name ?? '-',
                'semester' => $user->semester?->name ?? '-',
                'courses' => $courses,
            ]);
        } else {
            $body = $copy->get('profile.body_department', [
                'department' => $user->department_name ?: $copy->get('profile.none'),
                'university' => $user->university?->name ?? '-',
                'semester' => $user->semester?->name ?? '-',
            ]);
        }

        $this->telegram->sendMessage($chatId, $body, [
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

        if ($this->canAutoCompleteOnboarding($user)) {
            $this->finishOnboarding($user, $chatId);

            return;
        }

        $this->promptCurrentOnboardingStep($user, $chatId, $this->telegram->cachedInlineMessageId($user->id));
    }

    protected function canAutoCompleteOnboarding(User $user): bool
    {
        if (in_array($user->onboarding_step, [
            OnboardingStep::Start,
            OnboardingStep::Year,
            OnboardingStep::Stream,
            OnboardingStep::Department,
        ], true)) {
            return false;
        }

        if ($user->stream_id !== null) {
            return true;
        }

        $year = $user->year();

        return $year !== null
            && $year->slug !== YearSlug::Freshman->value
            && filled($user->department_name);
    }

    protected function promptCurrentOnboardingStep(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $year = $user->year();

        if ($year === null) {
            $user->update(['onboarding_step' => OnboardingStep::Year]);
            $this->askYear($user, $chatId, $messageId);

            return;
        }

        if ($year->slug === YearSlug::Freshman->value && $user->stream_id === null) {
            $user->update(['onboarding_step' => OnboardingStep::Stream]);
            $this->askStream($user, $chatId, $messageId);

            return;
        }

        if ($year->slug !== YearSlug::Freshman->value && blank($user->department_name)) {
            $user->update(['onboarding_step' => OnboardingStep::Department]);
            $this->askDepartment($user, $chatId, $messageId);

            return;
        }

        $this->finishOnboarding($user, $chatId, $messageId);
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

    protected function askYear(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $copy = TelegramCopy::for($user);
        $years = Year::query()->orderBy('sort_order')->orderBy('name')->get();

        if ($years->isEmpty()) {
            $result = $this->telegram->replyOrEdit($chatId, $copy->get('onboarding.year_unavailable'), messageId: $messageId);
            $this->telegram->rememberInlineMessage($user->id, $result, $messageId);

            return;
        }

        $rows = $years->map(fn (Year $year): array => [[
            'text' => $copy->get('onboarding.years.'.$year->slug),
            'callback_data' => "ob:year:{$year->id}",
            'style' => $year->slug === YearSlug::Freshman->value
                ? TelegramButtonStyle::Success->value
                : TelegramButtonStyle::Primary->value,
        ]])->values()->all();

        $this->sendOnboardingPrompt($user, $chatId, $copy->get('onboarding.year_prompt'), $rows, $messageId);
    }

    protected function askStream(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $copy = TelegramCopy::for($user);
        $streams = Stream::query()->active()->orderBy('name')->get();

        if ($streams->isEmpty()) {
            $result = $this->telegram->replyOrEdit($chatId, '📭 No streams are available yet. Please try again later.', messageId: $messageId);
            $this->telegram->rememberInlineMessage($user->id, $result, $messageId);

            return;
        }

        $rows = $streams->map(fn (Stream $stream) => [[
            'text' => $stream->name,
            'callback_data' => "ob:stream:{$stream->id}",
            'style' => match (Str::lower($stream->name)) {
                'natural' => TelegramButtonStyle::Success->value,
                default => TelegramButtonStyle::Primary->value,
            },
        ]])->values()->all();

        $this->sendOnboardingPrompt($user, $chatId, $copy->get('onboarding.stream_prompt'), $rows, $messageId);
    }

    protected function askDepartment(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $copy = TelegramCopy::for($user);

        $result = $this->telegram->replyOrEdit($chatId, $copy->get('onboarding.department_prompt'), [
            'reply_markup' => $this->telegram->inlineKeyboard([]),
        ], $messageId);

        $this->telegram->rememberInlineMessage($user->id, $result, $messageId);
    }

    protected function handleDepartmentReply(User $user, int|string $chatId, string $text): void
    {
        $copy = TelegramCopy::for($user);
        $department = trim($text);

        if ($department === '' || mb_strlen($department) > 255) {
            $this->telegram->sendMessage($chatId, $copy->get('onboarding.department_invalid'));
            $this->askDepartment($user, $chatId);

            return;
        }

        $user->update([
            'department_name' => $department,
        ]);

        $this->finishOnboarding($user->fresh() ?? $user, $chatId);
    }

    protected function handleOnboardingCallback(User $user, int|string $chatId, string $data, ?int $messageId): ?string
    {
        $copy = TelegramCopy::for($user);

        if ($user->onboarding_step === OnboardingStep::Complete) {
            return $copy->get('menu.onboarding_complete_menu');
        }

        if (str_starts_with($data, 'ob:year:')) {
            return $this->selectYear($user, $chatId, (int) Str::after($data, 'ob:year:'), $messageId);
        }

        if (str_starts_with($data, 'ob:stream:')) {
            return $this->selectStream($user, $chatId, (int) Str::after($data, 'ob:stream:'), $messageId);
        }

        return $copy->get('menu.stale_callback');
    }

    protected function selectYear(User $user, int|string $chatId, int $yearId, ?int $messageId): ?string
    {
        $copy = TelegramCopy::for($user);

        if (! in_array($user->onboarding_step, [OnboardingStep::Year, OnboardingStep::Start], true)) {
            return $copy->get('menu.stale_callback');
        }

        $year = Year::query()->find($yearId);

        if ($year === null) {
            $this->askYear($user, $chatId, $messageId);

            return $copy->get('onboarding.year_unavailable');
        }

        $user->syncYear($year);
        $user->unsetRelation('years');

        if ($year->slug === YearSlug::Freshman->value) {
            $user->update([
                'onboarding_step' => OnboardingStep::Stream,
                'stream_id' => null,
                'university_id' => null,
                'semester_id' => null,
            ]);
            cache()->forget("onboarding.courses.{$user->id}");
            $this->askStream($user->fresh() ?? $user, $chatId, $messageId);

            return null;
        }

        $user->update([
            'onboarding_step' => OnboardingStep::Department,
            'stream_id' => null,
            'university_id' => null,
            'semester_id' => null,
            'department_name' => null,
        ]);
        cache()->forget("onboarding.courses.{$user->id}");

        $this->askDepartment($user->fresh() ?? $user, $chatId, $messageId);

        return null;
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
            'university_id' => null,
            'semester_id' => null,
        ]);
        cache()->forget("onboarding.courses.{$user->id}");

        $this->finishOnboarding($user->fresh() ?? $user, $chatId, $messageId);

        return null;
    }

    protected function finishOnboardingAfterStream(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $this->finishOnboarding($user, $chatId, $messageId);
    }

    protected function finishOnboarding(User $user, int|string $chatId, ?int $messageId = null): void
    {
        $copy = TelegramCopy::for($user);
        $isFreshman = $user->isFreshman();

        if ($isFreshman) {
            $this->onboarding->syncAllActiveCourses($user);
        }

        $this->onboarding->complete($user);
        cache()->forget("onboarding.courses.{$user->id}");
        cache()->forget(TelegramService::InlineMessageCacheKey.$user->id);

        if ($messageId !== null) {
            $this->telegram->editMessageText($chatId, $messageId, $copy->get('menu.setup_finished'), [
                'reply_markup' => $this->telegram->inlineKeyboard([]),
            ]);
        }

        $doneKey = $isFreshman ? 'menu.onboarding_done' : 'menu.onboarding_done_department';
        $hintKey = $isFreshman ? 'menu.onboarding_profile_hint' : 'menu.onboarding_profile_hint_department';

        $this->telegram->sendMessage($chatId, $copy->get($doneKey), [
            'reply_markup' => $this->telegram->mainKeyboard($user),
        ]);

        $this->telegram->sendMessage($chatId, $copy->get($hintKey), [
            'reply_markup' => $this->telegram->inlineKeyboard([[
                [
                    'text' => $copy->get('menu.onboarding_profile_button'),
                    'web_app' => ['url' => $this->telegram->miniAppUrl('tg.profile.edit')],
                    'style' => TelegramButtonStyle::Success->value,
                ],
            ]]),
        ]);

        $user = $user->fresh();

        if ($user !== null) {
            $this->resumeStartPayload($user, $chatId);
        }
    }
}
