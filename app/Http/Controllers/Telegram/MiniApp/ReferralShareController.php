<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Enums\UserEventName;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReferralShareController extends Controller
{
    public function __invoke(UserEventService $userEvents): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $userEvents->log($user, UserEventName::ReferralLinkShared);

        return response()->json(['ok' => true]);
    }
}
