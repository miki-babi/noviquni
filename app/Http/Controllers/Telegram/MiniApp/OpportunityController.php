<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Enums\OnboardingStep;
use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\OpportunityGuidanceService;
use App\Services\TelegramDeepLink;
use App\Support\TelegramCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OpportunityController extends Controller
{
    public function show(Opportunity $opportunity, TelegramDeepLink $deepLinks): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        abort_unless($opportunity->is_published, 404);

        $isBookmarked = $user->bookmarks()
            ->whereMorphedTo('bookmarkable', $opportunity)
            ->exists();

        $deepLink = $deepLinks->forOpportunity($opportunity->id, $user);
        $shareText = $copy->get('opportunities.share_message', [
            'title' => $opportunity->title,
            'link' => $deepLink,
        ]);
        $shareText = trim(preg_replace("/\n{3,}/", "\n\n", $shareText) ?? $shareText);
        $shareUrl = 'https://t.me/share/url?url='.rawurlencode($deepLink).'&text='.rawurlencode($shareText);

        return view('telegram.mini-app.opportunity', [
            'copy' => $copy,
            'user' => $user,
            'opportunity' => $opportunity,
            'isBookmarked' => $isBookmarked,
            'shareUrl' => $shareUrl,
            'onboardingComplete' => $user->onboarding_step === OnboardingStep::Complete,
            'botOnboardingUrl' => $deepLinks->url(),
            'activeNav' => 'saved',
        ]);
    }

    public function toggle(Request $request, Opportunity $opportunity): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        abort_unless($opportunity->is_published, 404);

        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return back()
                ->with('status', $copy->get('opportunities.finish_onboarding_to_act'))
                ->with('show_onboarding_link', true);
        }

        $existing = $user->bookmarks()
            ->whereMorphedTo('bookmarkable', $opportunity)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return back()->with('status', $copy->get('saved.removed_status'));
        }

        $user->bookmarks()->firstOrCreate([
            'bookmarkable_type' => $opportunity->getMorphClass(),
            'bookmarkable_id' => $opportunity->id,
        ]);

        return back()->with('status', $copy->get('saved.saved_status'));
    }

    public function requestGuidance(
        Request $request,
        Opportunity $opportunity,
        OpportunityGuidanceService $guidance,
    ): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        abort_unless($opportunity->is_published && $opportunity->hasGuidanceAvailable(), 404);

        if ($user->onboarding_step !== OnboardingStep::Complete) {
            return back()
                ->with('status', $copy->get('opportunities.finish_onboarding_to_act'))
                ->with('show_onboarding_link', true);
        }

        $guidance->requestGuidance($user, $opportunity);

        return back()->with('status', $copy->get('opportunities.guidance_sent'));
    }
}
