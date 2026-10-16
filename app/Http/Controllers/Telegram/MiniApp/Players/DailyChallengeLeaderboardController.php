<?php

namespace App\Http\Controllers\Telegram\MiniApp\Players;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DailyChallengeService;
use App\Support\TelegramCopy;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DailyChallengeLeaderboardController extends Controller
{
    public function __invoke(DailyChallengeService $dailyChallenges): View
    {
        abort_unless((bool) config('daily_challenge.leaderboard_enabled'), 404);

        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);
        $challenge = $dailyChallenges->today();

        abort_unless($challenge !== null, 404);

        $board = $dailyChallenges->leaderboard($challenge, $user);
        $streak = $dailyChallenges->streakFor($user);

        return view('telegram.mini-app.players.daily-challenge-leaderboard', [
            'copy' => $copy,
            'user' => $user,
            'challenge' => $challenge,
            'rows' => $board['rows'],
            'userRank' => $board['user_rank'],
            'userAttempt' => $board['user_attempt'],
            'streak' => $streak,
            'title' => $copy->get('daily_challenge.leaderboard_button'),
            'backUrl' => route('tg.play.daily-challenge'),
            'playUrl' => route('tg.play.daily-challenge'),
            'premiumUrl' => route('tg.premium'),
        ]);
    }
}
