<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class HomeScreenController extends Controller
{
    public const int MaxPrompts = 3;

    public function prompted(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->home_screen_added_at !== null) {
            return response()->json([
                'ok' => true,
                'prompt_count' => (int) $user->home_screen_prompt_count,
                'added' => true,
            ]);
        }

        $count = (int) $user->home_screen_prompt_count;

        if ($count < self::MaxPrompts) {
            $user->home_screen_prompt_count = $count + 1;
            $user->save();
        }

        return response()->json([
            'ok' => true,
            'prompt_count' => (int) $user->home_screen_prompt_count,
            'added' => false,
        ]);
    }

    public function added(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->home_screen_added_at === null) {
            $user->home_screen_added_at = now();
            $user->save();
        }

        return response()->json([
            'ok' => true,
            'prompt_count' => (int) $user->home_screen_prompt_count,
            'added' => true,
        ]);
    }
}
