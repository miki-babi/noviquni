<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Enums\UserEventName;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\SettingsService;
use App\Services\UserEventService;
use App\Support\TelegramCopy;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PremiumController extends Controller
{
    public function show(SettingsService $settings, ReferralService $referrals, UserEventService $userEvents): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);
        $userEvents->log($user, UserEventName::PremiumView);

        return view('telegram.mini-app.premium', [
            'copy' => $copy,
            'user' => $user,
            'price' => $settings->premiumPrice(),
            'required' => $settings->requiredReferrals(),
            'progress' => $referrals->qualifiedCount($user),
            'urgency' => $settings->cohortUrgencyCopy(),
            'pitchTitle' => $settings->premiumPitchTitle(),
            'pitchBody' => $settings->premiumPitchBody(),
            'paymentBotUrl' => $settings->paymentBotUrl($user),
            'activeNav' => 'premium',
        ]);
    }
}
