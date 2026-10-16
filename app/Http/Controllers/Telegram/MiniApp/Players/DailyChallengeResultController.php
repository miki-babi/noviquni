<?php

namespace App\Http\Controllers\Telegram\MiniApp\Players;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DailyChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class DailyChallengeResultController extends Controller
{
    public function __invoke(Request $request, DailyChallengeService $dailyChallenges): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $challenge = $dailyChallenges->today();

        abort_unless($challenge !== null, 404);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'integer', 'min:0'],
            'duration_ms' => ['required', 'integer', 'min:0', 'max:86400000'],
        ]);

        try {
            $attempt = $dailyChallenges->recordAttempt(
                $user,
                $challenge,
                $validated['answers'],
                (int) $validated['duration_ms'],
            );
        } catch (InvalidArgumentException) {
            abort(422, 'This daily challenge has no scorable questions.');
        }

        $streak = $dailyChallenges->streakFor($user);

        return response()->json([
            'ok' => true,
            'score' => $attempt->score,
            'total' => $attempt->total,
            'duration_ms' => $attempt->duration_ms,
            'attempt_id' => $attempt->id,
            'streak' => (int) ($streak?->current_streak ?? 0),
            'badges' => $streak?->unlockedBadges() ?? [],
            'leaderboard_url' => config('daily_challenge.leaderboard_enabled')
                ? route('tg.play.daily-challenge.leaderboard')
                : null,
        ]);
    }
}
