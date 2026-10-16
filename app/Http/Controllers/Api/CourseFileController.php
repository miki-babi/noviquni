<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseFile;
use App\Support\LearningResourceFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

class CourseFileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);

        $paginator = CourseFile::query()
            ->with('course:id,slug')
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = collect($paginator->items())->map(
            fn (CourseFile $file): array => $this->listPayload($file),
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => [
                'required',
                File::types(LearningResourceFiles::acceptedExtensions())
                    ->max(LearningResourceFiles::MaxSizeKilobytes),
            ],
            'title' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_published' => ['sometimes', 'boolean'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'telegram_file_id' => ['nullable', 'string', 'max:255'],
        ]);

        $externalId = filled($validated['external_id'] ?? null)
            ? (string) $validated['external_id']
            : null;

        if ($externalId !== null) {
            $existing = CourseFile::query()
                ->with('course:id,slug')
                ->where('external_id', $externalId)
                ->first();

            if ($existing !== null) {
                return response()->json($this->storePayload($existing));
            }
        }

        $course = Course::query()->where('slug', $validated['course'])->first();

        if ($course === null) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'course' => ['The selected course is invalid.'],
                ],
            ], 422);
        }

        /** @var UploadedFile $upload */
        $upload = $validated['file'];
        $extension = strtolower($upload->getClientOriginalExtension() ?: $upload->extension() ?: 'bin');
        $directory = LearningResourceFiles::directoryForCourse($course->id);
        $path = $directory.'/'.Str::uuid()->toString().'.'.$extension;
        $disk = LearningResourceFiles::diskName();

        Storage::disk($disk)->put($path, (string) file_get_contents($upload->getRealPath()));

        $courseFile = CourseFile::query()->create([
            'course_id' => $course->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'path' => $path,
            'disk' => $disk,
            'size' => (int) $upload->getSize(),
            'mime' => $upload->getMimeType() ?: $upload->getClientMimeType(),
            'is_published' => (bool) ($validated['is_published'] ?? false),
            'external_id' => $externalId,
            'telegram_file_id' => $validated['telegram_file_id'] ?? null,
        ]);

        $courseFile->setRelation('course', $course);

        return response()->json($this->storePayload($courseFile), 201);
    }

    public function update(Request $request, CourseFile $courseFile): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'course' => ['sometimes', 'string', 'max:255'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('course', $validated)) {
            $course = Course::query()->where('slug', $validated['course'])->first();

            if ($course === null) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'course' => ['The selected course is invalid.'],
                    ],
                ], 422);
            }

            $courseFile->course_id = $course->id;
        }

        if (array_key_exists('title', $validated)) {
            $courseFile->title = $validated['title'];
        }

        if (array_key_exists('description', $validated)) {
            $courseFile->description = $validated['description'];
        }

        if (array_key_exists('is_published', $validated)) {
            $courseFile->is_published = $validated['is_published'];
        }

        $courseFile->save();
        $courseFile->load('course:id,slug');

        return response()->json($this->listPayload($courseFile));
    }

    /**
     * @return array{id: int, title: string, course: string|null, path: string, url: string|null, size: int, mime: string|null}
     */
    protected function storePayload(CourseFile $file): array
    {
        return [
            'id' => $file->id,
            'title' => $file->title,
            'course' => $file->course?->slug,
            'path' => $file->path,
            'url' => $file->url(),
            'size' => $file->size,
            'mime' => $file->mime,
        ];
    }

    /**
     * @return array{id: int, title: string, course: string|null, external_id: string|null, telegram_file_id: string|null, size: int, is_published: bool, url: string|null}
     */
    protected function listPayload(CourseFile $file): array
    {
        return [
            'id' => $file->id,
            'title' => $file->title,
            'course' => $file->course?->slug,
            'external_id' => $file->external_id,
            'telegram_file_id' => $file->telegram_file_id,
            'size' => $file->size,
            'is_published' => $file->is_published,
            'url' => $file->url(),
        ];
    }
}
