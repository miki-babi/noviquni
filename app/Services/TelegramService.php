<?php

namespace App\Services;

use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Enums\TelegramButtonStyle;
use App\Enums\UserEventName;
use App\Enums\UserRole;
use App\Enums\YearSlug;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\User;
use App\Models\Year;
use App\Support\TelegramCopy;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public const string InlineMessageCacheKey = 'telegram.onboarding_message.';

    public const string OnboardingPromptRateLimitKey = 'telegram.onboarding_prompt.';

    public function __construct(
        public NewStudentAdminNotifier $newStudentAdminNotifier,
        public UserEventService $userEvents,
    ) {}

    public function token(): ?string
    {
        return config('services.telegram.bot_token');
    }

    public function isConfigured(): bool
    {
        return filled($this->token());
    }

    /**
     * @return list<string>
     */
    public function fileAdminUsernames(): array
    {
        $raw = (string) config('services.telegram.file_admin_username', '');

        return collect(explode(',', $raw))
            ->map(fn (string $username): string => strtolower(ltrim(trim($username), '@')))
            ->filter()
            ->values()
            ->all();
    }

    public function isFileVaultAdmin(?string $username): bool
    {
        if (blank($username)) {
            return false;
        }

        $normalized = strtolower(ltrim(trim($username), '@'));

        return in_array($normalized, $this->fileAdminUsernames(), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function sendMessage(int|string $chatId, string $text, array $payload = []): ?array
    {
        return $this->call('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function sendDocument(int|string $chatId, string $fileUrlOrId, string $caption = '', array $payload = []): ?array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'caption' => $caption,
        ], $payload);

        if ($this->isLocalFilesystemPath($fileUrlOrId)) {
            return $this->callMultipart('sendDocument', $params, 'document', $fileUrlOrId);
        }

        $params['document'] = $fileUrlOrId;

        return $this->call('sendDocument', $params);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function sendPhoto(int|string $chatId, string $photoUrlPathOrId, string $caption = '', array $payload = []): ?array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $payload);

        if ($this->isLocalFilesystemPath($photoUrlPathOrId)) {
            return $this->callMultipart('sendPhoto', $params, 'photo', $photoUrlPathOrId);
        }

        $params['photo'] = $photoUrlPathOrId;

        return $this->call('sendPhoto', $params);
    }

    /**
     * Absolute Mini App URL (HTTPS host from APP_URL or TELEGRAM_MINI_APP_URL).
     *
     * @param  array<string, mixed>  $parameters
     */
    public function miniAppUrl(string $routeName, array $parameters = []): string
    {
        $url = route($routeName, $parameters, absolute: true);
        $override = config('services.telegram.mini_app_url');

        if (blank($override)) {
            return $url;
        }

        $parts = parse_url($url);
        $overrideParts = parse_url((string) $override);

        if (! is_array($parts) || ! is_array($overrideParts)) {
            return $url;
        }

        return ($overrideParts['scheme'] ?? 'https').'://'
            .($overrideParts['host'] ?? '')
            .(isset($overrideParts['port']) ? ':'.$overrideParts['port'] : '')
            .($parts['path'] ?? '')
            .(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * @return array{keyboard: array<int, array<int, array<string, mixed>>>, resize_keyboard: true}
     */
    public function mainKeyboard(?User $user = null): array
    {
        $copy = $user !== null ? TelegramCopy::for($user) : new TelegramCopy;

        $rows = [];

        if ($user?->isFreshman()) {
            $rows[] = [
                [
                    'text' => $copy->get('keyboard.resources'),
                    'style' => TelegramButtonStyle::Success->value,
                ],
            ];
        }

        $rows[] = [
            [
                'text' => $copy->get('keyboard.scholarships'),
                'style' => TelegramButtonStyle::Success->value,
            ],
        ];
        $rows[] = [
            [
                'text' => $copy->get('keyboard.saved'),
            ],
        ];
        $rows[] = [
            [
                'text' => $copy->get('keyboard.challenges'),
                'style' => TelegramButtonStyle::Success->value,
            ],
        ];
        $rows[] = [
            [
                'text' => $copy->get('keyboard.internships'),
                'style' => TelegramButtonStyle::Success->value,
            ],
            [
                'text' => $copy->get('keyboard.opportunities'),
                'style' => TelegramButtonStyle::Success->value,
            ],
        ];
        $rows[] = [
            [
                'text' => $copy->get('keyboard.mentorship'),
                'style' => TelegramButtonStyle::Success->value,
            ],
        ];
        $rows[] = [
            [
                'text' => $copy->get('keyboard.profile'),
            ],
            [
                'text' => $copy->get('keyboard.refer'),
            ],
        ];

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
        ];
    }

    /**
     * @return array{keyboard: array<int, array<int, array<string, string>>>, resize_keyboard: true}
     */
    public function courseKeyboard(User $user, ?ResourceType $resourceType = null): array
    {
        $courses = $user->courses()
            ->active()
            ->when(
                $resourceType !== null,
                fn ($query) => $query->whereHas(
                    'learningResources',
                    fn ($resourceQuery) => $resourceQuery->published()->where('type', $resourceType->value),
                ),
            )
            ->orderBy('courses.name')
            ->get();

        $rows = $courses
            ->map(fn (Course $course): array => [
                'text' => $this->courseKeyboardLabel($course),
                'style' => TelegramButtonStyle::Primary->value,
            ])
            ->chunk(2)
            ->map(fn ($row): array => $row->values()->all())
            ->all();

        $rows[] = [[
            'text' => $this->courseKeyboardBackLabel(),
        ]];

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
        ];
    }

    public function courseKeyboardLabel(Course $course): string
    {
        return '📗 '.$course->name;
    }

    public function courseKeyboardBackLabel(): string
    {
        return '← Back';
    }

    /**
     * @return array{keyboard: array<int, array<int, array<string, string>>>, resize_keyboard: true}
     */
    public function resourceTypeKeyboard(User $user): array
    {
        $rows = collect(ResourceType::creatableCases())
            ->filter(fn (ResourceType $type): bool => $this->userHasResourcesForType($user, $type))
            ->values()
            ->map(function (ResourceType $type, int $index): array {
                $style = match (true) {
                    $type === ResourceType::ReferenceBooks => TelegramButtonStyle::Danger,
                    $index % 2 === 0 => TelegramButtonStyle::Success,
                    default => TelegramButtonStyle::Primary,
                };

                return [
                    'text' => $this->resourceTypeKeyboardLabel($type),
                    'style' => $style->value,
                ];
            })
            ->chunk(2)
            ->map(fn ($row): array => $row->values()->all())
            ->all();

        $rows[] = [[
            'text' => $this->courseKeyboardBackLabel(),
        ]];

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
        ];
    }

    public function resourceTypeForKeyboardLabel(User $user, string $label): ?ResourceType
    {
        return collect(ResourceType::creatableCases())
            ->first(fn (ResourceType $type): bool => $this->userHasResourcesForType($user, $type)
                && $this->resourceTypeKeyboardLabel($type) === $label);
    }

    public function resourceTypeKeyboardLabel(ResourceType $type): string
    {
        return '📚 '.$type->label();
    }

    /**
     * @return array{keyboard: array<int, array<int, array<string, string>>>, resize_keyboard: true}
     */
    public function savedKeyboard(User $user): array
    {
        $copy = TelegramCopy::for($user);

        $rows = collect($this->savedTabKeys())
            ->map(fn (string $tab): array => [
                'text' => $this->savedTabKeyboardLabel($copy, $tab),
                'style' => TelegramButtonStyle::Primary->value,
            ])
            ->chunk(2)
            ->map(fn ($row): array => $row->values()->all())
            ->all();

        $rows[] = [[
            'text' => $this->courseKeyboardBackLabel(),
        ]];

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
        ];
    }

    public function savedTabForKeyboardLabel(User $user, string $label): ?string
    {
        $copy = TelegramCopy::for($user);

        return collect($this->savedTabKeys())
            ->first(fn (string $tab): bool => $this->savedTabKeyboardLabel($copy, $tab) === $label);
    }

    public function savedTabKeyboardLabel(TelegramCopy $copy, string $tab): string
    {
        return match ($tab) {
            'resources' => $copy->get('saved.tab_resources'),
            'scholarships' => $copy->get('saved.tab_scholarships'),
            'internships' => $copy->get('saved.tab_internships'),
            'jobs' => $copy->get('saved.tab_jobs'),
            'mentorship' => $copy->get('saved.tab_mentorship'),
            default => $copy->get('saved.tab_resources'),
        };
    }

    /**
     * @return list<string>
     */
    public function savedTabKeys(): array
    {
        return ['resources', 'scholarships', 'internships', 'jobs', 'mentorship'];
    }

    protected function userHasResourcesForType(User $user, ResourceType $type): bool
    {
        return LearningResource::query()
            ->published()
            ->whereIn('course_id', $user->courses()->active()->select('courses.id'))
            ->where('type', $type->value)
            ->orderBy('sort_order')
            ->exists();
    }

    /**
     * Default bot menu button that opens the Courses Mini App (supplies initData).
     */
    public function setChatMenuButtonWebApp(string $text, string $url): ?array
    {
        return $this->call('setChatMenuButton', [
            'menu_button' => [
                'type' => 'web_app',
                'text' => $text,
                'web_app' => ['url' => $url],
            ],
        ]);
    }

    public function syncDefaultMiniAppMenuButton(?User $user = null): void
    {
        $copy = $user !== null ? TelegramCopy::for($user) : new TelegramCopy;

        $this->setChatMenuButtonWebApp(
            $copy->get('menu.courses'),
            $this->miniAppUrl('tg.browse'),
        );
    }

    /**
     * @param  array<int, array<int, array<string, string>>>  $rows
     * @return array{inline_keyboard: array<int, array<int, array<string, string>>>}
     */
    public function inlineKeyboard(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function editMessageText(int|string $chatId, int $messageId, string $text, array $payload = []): ?array
    {
        return $this->call('editMessageText', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $payload));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function deleteMessage(int|string $chatId, int $messageId): ?array
    {
        return $this->call('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $replyMarkup
     * @return array<string, mixed>|null
     */
    public function editMessageReplyMarkup(int|string $chatId, int $messageId, array $replyMarkup): ?array
    {
        return $this->call('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ]);
    }

    /**
     * Send a new message or edit an existing one when messageId is present.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function replyOrEdit(int|string $chatId, string $text, array $payload = [], ?int $messageId = null): ?array
    {
        if ($messageId !== null) {
            return $this->editMessageText($chatId, $messageId, $text, $payload);
        }

        return $this->sendMessage($chatId, $text, $payload);
    }

    public function rememberInlineMessage(int $userId, ?array $result, ?int $messageId = null): void
    {
        $resolvedMessageId = $messageId ?? (isset($result['message_id']) ? (int) $result['message_id'] : null);

        if ($resolvedMessageId === null) {
            return;
        }

        cache()->put(self::InlineMessageCacheKey.$userId, $resolvedMessageId, now()->addHour());
    }

    public function cachedInlineMessageId(int $userId): ?int
    {
        $messageId = cache()->get(self::InlineMessageCacheKey.$userId);

        return is_int($messageId) ? $messageId : null;
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): ?array
    {
        $params = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $params['text'] = $text;
            $params['show_alert'] = $showAlert;
        }

        return $this->call('answerCallbackQuery', $params);
    }

    public function botApi(): TelegramBotApi
    {
        return new TelegramBotApi($this->token(), 'TELEGRAM_BOT_TOKEN is not set.');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getWebhookInfo(): ?array
    {
        return $this->botApi()->getWebhookInfo();
    }

    /**
     * @return array{ok: bool, description?: string, result?: array<string, mixed>|bool|null, error?: array<string, mixed>|null}
     */
    public function setWebhook(string $url): array
    {
        return $this->botApi()->setWebhook($url);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    public function call(string $method, array $params = []): ?array
    {
        $outcome = $this->botApi()->execute($method, $params);

        if (! $outcome['ok']) {
            $this->logStartOutbound($method, $params, false, $outcome['body']);
            $this->logBlockedIfNeeded($method, $params, $outcome['body']);

            return null;
        }

        $this->logStartOutbound($method, $params, true, $outcome['result']);

        return $outcome['result'];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    protected function callMultipart(string $method, array $params, string $fileField, string $filePath): ?array
    {
        if (! $this->isConfigured()) {
            Log::warning('Telegram bot token not configured.', ['method' => $method]);

            return null;
        }

        if (isset($params['reply_markup']) && is_array($params['reply_markup'])) {
            $params['reply_markup'] = json_encode($params['reply_markup'], JSON_THROW_ON_ERROR);
        }

        $contents = file_get_contents($filePath);

        if ($contents === false) {
            Log::error('Telegram multipart upload failed to read file.', [
                'method' => $method,
                'path' => $filePath,
            ]);

            return null;
        }

        $response = Http::timeout(30)
            ->attach($fileField, $contents, basename($filePath))
            ->post("https://api.telegram.org/bot{$this->token()}/{$method}", $params);

        if (! $response->successful() || ! ($response->json('ok') ?? false)) {
            $body = $response->json();
            Log::error('Telegram API error', [
                'method' => $method,
                'body' => $body,
            ]);
            $this->logStartOutbound($method, $params, false, is_array($body) ? $body : null, basename($filePath));
            $this->logBlockedIfNeeded($method, $params, is_array($body) ? $body : null);

            return null;
        }

        $result = $response->json('result');
        $this->logStartOutbound($method, $params, true, is_array($result) ? $result : null, basename($filePath));

        return is_array($result) ? $result : null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>|null  $telegramResult
     */
    protected function logStartOutbound(string $method, array $params, bool $ok, ?array $telegramResult = null, ?string $fileName = null): void
    {
        if (Context::get('telegram_command') !== 'start') {
            return;
        }

        if (! in_array($method, ['sendMessage', 'sendPhoto', 'sendDocument', 'editMessageText'], true)) {
            return;
        }

        $replyMarkup = $params['reply_markup'] ?? null;

        if (is_string($replyMarkup)) {
            $decoded = json_decode($replyMarkup, true);
            $replyMarkup = is_array($decoded) ? $decoded : $replyMarkup;
        }

        $context = [
            'method' => $method,
            'chat_id' => $params['chat_id'] ?? null,
            'text' => $params['text'] ?? null,
            'caption' => $params['caption'] ?? null,
            'parse_mode' => $params['parse_mode'] ?? null,
            'reply_markup' => $replyMarkup,
            'file_name' => $fileName,
            'ok' => $ok,
            'telegram_message_id' => is_array($telegramResult) ? ($telegramResult['message_id'] ?? null) : null,
        ];

        if (! $ok) {
            $context['error'] = $telegramResult;
        }

        Log::info('Telegram start message sent', $context);
    }

    protected function isLocalFilesystemPath(string $value): bool
    {
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return false;
        }

        return is_file($value);
    }

    public function findOrCreateStudent(int|string $telegramId, string $name, ?string $username = null): User
    {
        $user = User::query()->where('telegram_id', (string) $telegramId)->first();

        if ($user !== null) {
            $user->update([
                'name' => $name,
                'telegram_username' => $username,
            ]);

            return $user->refresh();
        }

        $user = User::query()->create([
            'name' => $name,
            'telegram_id' => (string) $telegramId,
            'telegram_username' => $username,
            'role' => UserRole::Student,
            'onboarding_step' => OnboardingStep::Start,
            'is_active' => true,
        ]);

        $freshman = Year::query()->where('slug', YearSlug::Freshman->value)->first();

        if ($freshman !== null) {
            $user->syncYear($freshman);
        }

        $this->newStudentAdminNotifier->notify($user, $this);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>|null  $body
     */
    protected function logBlockedIfNeeded(string $method, array $params, ?array $body): void
    {
        if ($body === null || ! $this->isBlockedByUserResponse($body)) {
            return;
        }

        $chatId = $params['chat_id'] ?? null;

        if ($chatId === null || $chatId === '') {
            return;
        }

        $user = User::query()->where('telegram_id', (string) $chatId)->first();

        if ($user === null) {
            return;
        }

        $this->userEvents->log($user, UserEventName::Blocked, [
            'method' => $method,
            'description' => (string) ($body['description'] ?? ''),
            'error_code' => $body['error_code'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function isBlockedByUserResponse(array $body): bool
    {
        $errorCode = (int) ($body['error_code'] ?? 0);
        $description = strtolower((string) ($body['description'] ?? ''));

        return $errorCode === 403 && str_contains($description, 'blocked');
    }
}
