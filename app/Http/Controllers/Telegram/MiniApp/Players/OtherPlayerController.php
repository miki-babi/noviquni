<?php

namespace App\Http\Controllers\Telegram\MiniApp\Players;

use App\Enums\ResourceType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Telegram\MiniApp\Concerns\OpensMiniAppResource;
use App\Models\LearningResource;
use App\Services\College\TelegramResourceFormatter;
use App\Services\PremiumService;
use App\Services\ReferralService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OtherPlayerController extends Controller
{
    use OpensMiniAppResource;

    public function __invoke(
        LearningResource $resource,
        PremiumService $premium,
        SettingsService $settings,
        ReferralService $referrals,
        TelegramResourceFormatter $formatter,
    ): View|RedirectResponse {
        return $this->openCatalogPlayer(
            $resource,
            ResourceType::Other,
            'telegram.mini-app.players.other',
            $premium,
            $settings,
            $referrals,
            $formatter,
        );
    }
}
