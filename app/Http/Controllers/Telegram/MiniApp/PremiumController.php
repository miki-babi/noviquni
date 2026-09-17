<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\ReferralService;
use App\Services\SettingsService;
use App\Support\TelegramCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PremiumController extends Controller
{
    public function show(SettingsService $settings, ReferralService $referrals): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        return view('telegram.mini-app.premium', [
            'copy' => $copy,
            'user' => $user,
            'price' => $settings->premiumPrice(),
            'required' => $settings->requiredReferrals(),
            'progress' => $referrals->qualifiedCount($user),
            'urgency' => $settings->cohortUrgencyCopy(),
            'activeNav' => 'premium',
        ]);
    }

    public function pay(PaymentService $payments): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->hasActivePremium()) {
            return back();
        }

        $payment = $payments->createPendingPremiumPayment($user);

        return back()->with('payment_instructions', $payments->instructionsFor($payment));
    }
}
