<?php

namespace App\Http\Controllers\Telegram\MiniApp\Players;

use App\Enums\ResourceType;
use App\Enums\UserEventName;
use App\Http\Controllers\Controller;
use App\Models\LearningResource;
use App\Models\User;
use App\Services\UserEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizResultController extends Controller
{
    public function __invoke(
        Request $request,
        LearningResource $resource,
        UserEventService $userEvents,
    ): JsonResponse {
        abort_unless($resource->type === ResourceType::Quiz && $resource->is_published, 404);

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1'],
        ]);

        $score = min((int) $validated['score'], (int) $validated['total']);

        $userEvents->log($user, UserEventName::QuizDone, $userEvents->resourceStudyDetails($resource, [
            'score' => $score,
            'total' => (int) $validated['total'],
        ]));

        return response()->json(['ok' => true]);
    }
}
