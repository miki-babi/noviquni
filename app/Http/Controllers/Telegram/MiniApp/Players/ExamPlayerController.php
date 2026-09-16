<?php

namespace App\Http\Controllers\Telegram\MiniApp\Players;

use App\Enums\CollegeResourceKind;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Telegram\MiniApp\Concerns\OpensMiniAppResource;
use App\Models\LearningResource;
use App\Services\PremiumService;
use App\Services\ReferralService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExamPlayerController extends Controller
{
    use OpensMiniAppResource;

    public function __invoke(
        LearningResource $resource,
        PremiumService $premium,
        SettingsService $settings,
        ReferralService $referrals,
    ): View|RedirectResponse {
        abort_unless($resource->studyKind() === CollegeResourceKind::Exam, 404);

        $payload = $resource->playerPayload();
        abort_unless($payload !== null, 404);

        $access = $this->authorizeMiniAppResource($resource, $premium, $settings, $referrals);

        if ($access instanceof RedirectResponse || $access instanceof View) {
            return $access;
        }

        return view('telegram.mini-app.players.exam', [
            'copy' => $access['copy'],
            'user' => $access['user'],
            'resource' => $resource,
            'course' => $resource->course,
            'payload' => $payload,
            'backUrl' => $resource->course
                ? route('tg.courses.show', $resource->course)
                : route('tg.browse'),
        ]);
    }
}
