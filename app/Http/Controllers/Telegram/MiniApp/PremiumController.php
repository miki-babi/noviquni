<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Enums\PaymentStatus;
use App\Enums\UserEventName;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\ReferralService;
use App\Services\SettingsService;
use App\Services\UserEventService;
use App\Services\VerifyCheckout\VerifyCheckoutException;
use App\Support\TelegramCopy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class PremiumController extends Controller
{
    public function show(SettingsService $settings, ReferralService $referrals, UserEventService $userEvents): View|RedirectResponse
    {
        if (! config('services.telegram.premium_enabled')) {
            return redirect()->route('tg.profile');
        }

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
            'activeNav' => 'premium',
        ]);
    }

    public function pay(PaymentService $payments): RedirectResponse
    {
        if (! config('services.telegram.premium_enabled')) {
            return redirect()->route('tg.profile');
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->hasActivePremium()) {
            return back();
        }

        try {
            $payment = $payments->startVerifyCheckoutPremium($user);
        } catch (VerifyCheckoutException $exception) {
            Log::warning('verify_checkout.pay_failed', [
                'user_id' => $user->id,
                'error_code' => $exception->errorCode,
                'request_id' => $exception->requestId,
            ]);

            return back()->with('status', $exception->customerSafeMessage());
        }

        if (blank($payment->checkoutUrl)) {
            return back()->with('status', 'We could not start checkout. Please try again.');
        }

        return redirect()->away($payment->checkoutUrl);
    }

    public function return(Request $request, Payment $payment, PaymentService $payments): View
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($payment->isVerifyCheckout(), 404);

        try {
            $payment = $payments->reconcilePayment($payment);
        } catch (VerifyCheckoutException) {
            // Return page still shows local state; webhook/reconciliation will catch up.
            $payment = $payment->fresh() ?? $payment;
        }

        $payment->loadMissing('user');

        return view('telegram.mini-app.premium-return', [
            'payment' => $payment,
            'user' => $payment->user,
            'statusUrl' => URL::signedRoute('tg.premium.status', ['payment' => $payment->id]),
            'premiumUrl' => route('tg.premium'),
            'profileUrl' => route('tg.profile'),
        ]);
    }

    public function status(Request $request, Payment $payment, PaymentService $payments): JsonResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($payment->isVerifyCheckout(), 404);

        if ($payment->status === PaymentStatus::Pending && $payment->fulfilled_at === null) {
            try {
                $payment = $payments->reconcilePayment($payment);
            } catch (VerifyCheckoutException) {
                $payment = $payment->fresh() ?? $payment;
            }
        }

        $payment->loadMissing('user');

        return response()->json([
            'payment_id' => $payment->id,
            'status' => $payment->status->value,
            'deposit_status' => $payment->deposit_status,
            'fulfilled' => $payment->fulfilled_at !== null,
            'premium_active' => $payment->user?->hasActivePremium() ?? false,
            'premium_until' => $payment->user?->premium_until?->toIso8601String(),
        ]);
    }
}
