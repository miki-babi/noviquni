<?php

namespace App\Http\Controllers\Telegram\MiniApp\Players;

use App\Enums\QuestionReportReason;
use App\Enums\QuestionReportSource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\QuestionReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuestionReportController extends Controller
{
    public function __invoke(Request $request, QuestionReportService $reports): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'source' => ['required', 'string', Rule::enum(QuestionReportSource::class)],
            'learning_resource_id' => ['nullable', 'integer', 'required_unless:source,daily_challenge', 'exists:learning_resources,id'],
            'daily_challenge_id' => ['nullable', 'integer', 'required_if:source,daily_challenge', 'exists:daily_challenges,id'],
            'question_index' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', Rule::enum(QuestionReportReason::class)],
            'note' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf(fn (): bool => $request->input('reason') === QuestionReportReason::Other->value),
                Rule::when(
                    $request->input('reason') === QuestionReportReason::Other->value,
                    ['min:3'],
                ),
            ],
        ]);

        try {
            $report = $reports->report($user, $validated);
        } catch (HttpException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], $exception->getStatusCode());
        }

        return response()->json([
            'ok' => true,
            'report_id' => $report->id,
        ]);
    }
}
