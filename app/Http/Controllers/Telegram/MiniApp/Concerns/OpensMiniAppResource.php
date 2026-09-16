<?php

namespace App\Http\Controllers\Telegram\MiniApp\Concerns;

use App\Enums\ResourceHub;
use App\Enums\ResourceType;
use App\Models\LearningResource;
use App\Models\User;
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
     * @return array{user: User, copy: TelegramCopy}|RedirectResponse|View
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
                'activeNav' => 'browse',
            ]);
        }

        $user->downloads()->create(['learning_resource_id' => $resource->id]);

        return [
            'user' => $user,
            'copy' => $copy,
        ];
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
        ], true);

        if (! $isNotesOrModule) {
            return false;
        }

        return LearningResource::query()
            ->published()
            ->where('course_id', $resource->course_id)
            ->whereIn('type', ResourceHub::Practice->typeValues())
            ->exists();
    }
}
