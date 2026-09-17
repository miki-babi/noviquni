<?php

namespace App\Http\Controllers\Telegram\MiniApp\Concerns;

use App\Enums\CollegeResourceKind;
use App\Enums\ResourceType;
use App\Models\LearningResource;
use App\Models\User;
use App\Services\College\TelegramResourceFormatter;
use App\Services\CoursePathService;
use App\Services\PremiumService;
use App\Services\ReferralService;
use App\Services\SettingsService;
use App\Support\TelegramCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

trait OpensMiniAppResource
{
    /**
     * @return array{user: User, copy: TelegramCopy, isBookmarked: bool}|RedirectResponse|View
     */
    protected function authorizeMiniAppResource(
        LearningResource $resource,
        PremiumService $premium,
        SettingsService $settings,
        ReferralService $referrals,
    ): array|RedirectResponse|View {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        abort_unless($resource->is_published, 404);

        $resource->loadMissing('course');

        if ($resource->course_id !== null && ! $user->courses()->where('courses.id', $resource->course_id)->exists()) {
            return redirect()->route('tg.browse');
        }

        if (! $premium->canAccess($user, $resource)) {
            return view('telegram.mini-app.resource-locked', [
                'copy' => $copy,
                'user' => $user,
                'resource' => $resource,
                'price' => $settings->premiumPrice(),
                'required' => $settings->requiredReferrals(),
                'progress' => $referrals->qualifiedCount($user),
                'activeNav' => 'courses',
            ]);
        }

        $user->downloads()->create(['learning_resource_id' => $resource->id]);

        return [
            'user' => $user,
            'copy' => $copy,
            'isBookmarked' => $user->bookmarks()
                ->where('learning_resource_id', $resource->id)
                ->exists(),
        ];
    }

    protected function openCatalogPlayer(
        LearningResource $resource,
        ResourceType $expectedType,
        string $view,
        PremiumService $premium,
        SettingsService $settings,
        ReferralService $referrals,
        TelegramResourceFormatter $formatter,
        ?CollegeResourceKind $interactiveKind = null,
    ): View|RedirectResponse {
        abort_unless($resource->type === $expectedType, 404);

        $access = $this->authorizeMiniAppResource($resource, $premium, $settings, $referrals);

        if ($access instanceof RedirectResponse || $access instanceof View) {
            return $access;
        }

        $payload = null;

        if ($interactiveKind !== null
            && $resource->studyKind() === $interactiveKind
            && ($playerPayload = $resource->playerPayload()) !== null) {
            $payload = $playerPayload;
        }

        return view($view, [
            'copy' => $access['copy'],
            'user' => $access['user'],
            'resource' => $resource,
            'course' => $resource->course,
            'payload' => $payload,
            'chunks' => $payload === null ? $formatter->format($resource) : [],
            'showQuizNudge' => $this->shouldShowQuizNudge($resource),
            'pathNudgeUrl' => $this->pathNudgeUrl($resource),
            'isBookmarked' => $access['isBookmarked'],
            'backUrl' => $resource->course
                ? route('tg.courses.show', $resource->course)
                : route('tg.browse'),
        ]);
    }

    protected function shouldShowQuizNudge(LearningResource $resource): bool
    {
        if ($resource->course_id === null) {
            return false;
        }

        $isNotesOrModule = in_array($resource->type, [
            ResourceType::LectureNotes,
            ResourceType::Summary,
            ResourceType::Module,
            ResourceType::Worksheet,
        ], true);

        if (! $isNotesOrModule) {
            return false;
        }

        $next = app(CoursePathService::class)->nextAfter(Auth::user(), $resource);

        return $next !== null && in_array($next->type, [
            ResourceType::Worksheet,
            ResourceType::PracticeQuestion,
            ResourceType::Flashcards,
        ], true);
    }

    protected function pathNudgeUrl(LearningResource $resource): ?string
    {
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        $next = app(CoursePathService::class)->nextAfter($user, $resource);

        return $next?->miniAppUrl();
    }
}
