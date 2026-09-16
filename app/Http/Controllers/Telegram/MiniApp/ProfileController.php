<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Enums\TelegramLocale;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReferralService;
use App\Support\TelegramCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(ReferralService $referrals): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);
        $user->load(['stream', 'university', 'semester', 'courses']);

        $premium = $user->hasActivePremium()
            ? $copy->get('profile.premium_yes', ['until' => $user->premium_until])
            : $copy->get('profile.premium_no');

        $courses = $user->courses->pluck('name')->implode(', ') ?: $copy->get('profile.none');

        return view('telegram.mini-app.profile', [
            'copy' => $copy,
            'user' => $user,
            'premium' => $premium,
            'courses' => $courses,
            'referralLink' => $referrals->referralLink($user),
            'activeNav' => 'profile',
        ]);
    }

    public function toggleNotifications(): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $user->update(['notifications_enabled' => ! $user->notifications_enabled]);
        $user->refresh();
        $copy = TelegramCopy::for($user);
        $state = $user->notifications_enabled
            ? $copy->get('notify.on')
            : $copy->get('notify.off');

        return back()->with('status', $copy->get('notify.toggled', ['state' => $state]));
    }

    public function enableNotifications(): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        if ($user->notifications_enabled) {
            return back()->with('status', $copy->get('notify.already'));
        }

        $user->update(['notifications_enabled' => true]);

        return back()->with('status', $copy->get('notify.enabled'));
    }

    public function updateLocale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:en,am'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $locale = TelegramLocale::from($validated['locale']);
        $user->update(['telegram_locale' => $locale->value]);

        $copy = TelegramCopy::for($user->fresh());

        return back()->with('status', $copy->get('settings.saved'));
    }
}
