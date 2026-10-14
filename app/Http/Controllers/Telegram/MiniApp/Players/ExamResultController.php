<?php

namespace App\Http\Controllers\Telegram\MiniApp\Players;

use App\Enums\AssessmentKind;
use App\Enums\ResourceType;
use App\Http\Controllers\Controller;
use App\Models\LearningResource;
use App\Models\User;
use App\Services\AssessmentAttemptRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ExamResultController extends Controller
{
    public function __invoke(
        Request $request,
        LearningResource $resource,
        AssessmentAttemptRecorder $recorder,
    ): JsonResponse {
        abort_unless(
            in_array($resource->type, [ResourceType::PracticeExams, ResourceType::PastExams], true)
                && $resource->is_published,
            404,
        );

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'integer', 'min:0'],
            'timed_out' => ['sometimes', 'boolean'],
        ]);

        try {
            $attempt = $recorder->record(
                $user,
                $resource,
                AssessmentKind::Exam,
                $validated['answers'],
                (bool) ($validated['timed_out'] ?? false),
            );
        } catch (InvalidArgumentException) {
            abort(422, 'This exam has no scorable questions.');
        }

        return response()->json([
            'ok' => true,
            'score' => $attempt->score,
            'total' => $attempt->total,
            'attempt_id' => $attempt->id,
        ]);
    }
}
