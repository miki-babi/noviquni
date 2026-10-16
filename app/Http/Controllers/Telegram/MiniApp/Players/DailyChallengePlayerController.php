<?php

namespace App\Http\Controllers\Telegram\MiniApp\Players;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DailyChallengeService;
use App\Support\TelegramCopy;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DailyChallengePlayerController extends Controller
{
    public function __invoke(DailyChallengeService $dailyChallenges): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);
        $challenge = $dailyChallenges->today();

        abort_unless($challenge !== null, 404);

        $attempt = $dailyChallenges->attemptFor($user, $challenge);
        $streak = $dailyChallenges->streakFor($user);

        $leaderboardEnabled = (bool) config('daily_challenge.leaderboard_enabled');

        return view('telegram.mini-app.players.daily-challenge', [
            'copy' => $copy,
            'user' => $user,
            'challenge' => $challenge,
            'payload' => $challenge->playerPayload(),
            'attempt' => $attempt,
            'streak' => $streak,
            'title' => $copy->get('daily_challenge.open_button'),
            'backUrl' => route('tg.home'),
            'leaderboardEnabled' => $leaderboardEnabled,
            'leaderboardUrl' => $leaderboardEnabled
                ? route('tg.play.daily-challenge.leaderboard')
                : null,
            'resultUrl' => route('tg.play.daily-challenge.result'),
            'premiumUrl' => route('tg.premium'),
        ]);
    }
}
