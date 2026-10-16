<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseFile;
use App\Models\TelegramFileAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TelegramFileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'unmigrated' => ['sometimes', 'boolean'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);

        $query = TelegramFileAsset::query()->orderByDesc('id');

        if ($request->boolean('unmigrated')) {
            $query->whereNull('course_file_id')->whereNull('stored_path');
        }

        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(
            fn (TelegramFileAsset $asset): array => $this->payload($asset),
        )->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function update(Request $request, TelegramFileAsset $telegramFileAsset): JsonResponse
    {
        $validated = $request->validate([
            'stored_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'course_file_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('course_files', 'id'),
            ],
        ]);

        if (array_key_exists('stored_path', $validated)) {
            $telegramFileAsset->stored_path = $validated['stored_path'];
        }

        if (array_key_exists('course_file_id', $validated)) {
            $telegramFileAsset->course_file_id = $validated['course_file_id'];

            if ($validated['course_file_id'] !== null && blank($telegramFileAsset->stored_path)) {
                $courseFile = CourseFile::query()->find($validated['course_file_id']);

                if ($courseFile !== null && filled($courseFile->path)) {
                    $telegramFileAsset->stored_path = $courseFile->path;
                }
            }
        }

        $telegramFileAsset->save();

        return response()->json($this->payload($telegramFileAsset->fresh() ?? $telegramFileAsset));
    }

    /**
     * @return array{
     *     id: int,
     *     file_name: string|null,
     *     telegram_file_id: string,
     *     file_unique_id: string,
     *     file_size: int|null,
     *     mime: string|null,
     *     uploaded_by: string|null,
     *     received_at: string|null,
     *     stored_path: string|null,
     *     course_file_id: int|null
     * }
     */
    protected function payload(TelegramFileAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'file_name' => $asset->file_name,
            'telegram_file_id' => $asset->file_id,
            'file_unique_id' => $asset->file_unique_id,
            'file_size' => $asset->file_size,
            'mime' => $asset->mime_type,
            'uploaded_by' => $asset->uploaded_by_username,
            'received_at' => $asset->created_at?->toIso8601String(),
            'stored_path' => $asset->stored_path,
            'course_file_id' => $asset->course_file_id,
        ];
    }
}
