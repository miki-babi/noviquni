<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Enums\TelegramLocale;
use App\Http\Controllers\Controller;
use App\Models\LearningResource;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\SettingsService;
use App\Services\TelegramDeepLink;
use App\Support\TelegramCopy;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(ReferralService $referrals, SettingsService $settings, TelegramDeepLink $deepLinks): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);
        $user->load('courses');

        $bookmarks = $user->bookmarks()
            ->with(['bookmarkable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    LearningResource::class => ['course'],
                ]);
            }])
            ->latest('id')
            ->limit(8)
            ->get()
            ->filter(function ($bookmark): bool {
                $item = $bookmark->bookmarkable;

                return ($item instanceof LearningResource && $item->is_published)
                    || ($item instanceof Opportunity && $item->is_published);
            })
            ->values();

        return view('telegram.mini-app.profile', [
            'copy' => $copy,
            'user' => $user,
            'courses' => $user->courses,
            'bookmarks' => $bookmarks,
            'deepLinks' => $deepLinks,
            'referralLink' => $referrals->referralLink($user),
            'qualifiedCount' => $referrals->qualifiedCount($user),
            'requiredReferrals' => $settings->requiredReferrals(),
            'referralPoints' => $user->referral_points,
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

    public function updateTheme(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $user->update(['theme' => $validated['theme']]);

        $copy = TelegramCopy::for($user->fresh());

        return back()->with('status', $copy->get('settings.theme_saved'));
    }
}
