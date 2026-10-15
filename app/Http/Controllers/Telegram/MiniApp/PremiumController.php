<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Enums\UserEventName;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\UserEventService;
use App\Support\TelegramCopy;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PremiumController extends Controller
{
    public function show(SettingsService $settings, UserEventService $userEvents): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);
        $userEvents->log($user, UserEventName::PremiumView);

        return view('telegram.mini-app.premium', [
            'copy' => $copy,
            'user' => $user,
            'price' => $settings->premiumPrice(),
            'paymentBotUrl' => $settings->paymentBotUrl($user),
            'activeNav' => 'premium',
        ]);
    }
}
