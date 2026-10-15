<?php

namespace App\Http\Controllers\Api;

use App\Enums\ResourceType;
use App\Filament\Resources\Learnings\LearningResource as LearningFilamentResource;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LearningResource;
use App\Services\Study\QuizContentNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LearningImportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);

        $paginator = LearningResource::query()
            ->with('course:id,slug')
            ->whereIn('type', [ResourceType::Quiz, ResourceType::PracticeExams])
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = collect($paginator->items())->map(function (LearningResource $resource): array {
            $questions = $resource->content['payload']['questions'] ?? [];

            return [
                'id' => $resource->id,
                'slug' => $resource->slug,
                'title' => $resource->title,
                'type' => $resource->type->value,
                'course' => $resource->course?->slug,
                'is_published' => $resource->is_published,
                'question_count' => is_array($questions) ? count($questions) : 0,
            ];
        })->values();

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

    public function store(Request $request, QuizContentNormalizer $normalizer): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in([ResourceType::Quiz->value, ResourceType::PracticeExams->value])],
            'course' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['sometimes', 'boolean'],
            'is_premium' => ['sometimes', 'boolean'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'array'],
            'content.payload' => ['required', 'array'],
            'content.payload.questions' => ['required', 'array', 'min:1'],
            'content.payload.title' => ['nullable', 'string', 'max:255'],
            'content.payload.instructions' => ['nullable', 'string'],
        ]);

        $externalId = filled($validated['external_id'] ?? null)
            ? (string) $validated['external_id']
            : null;

        if ($externalId !== null) {
            $existingByExternal = LearningResource::query()
                ->where('external_id', $externalId)
                ->first();

            if ($existingByExternal !== null) {
                return response()->json($this->resourcePayload($existingByExternal));
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

        $duplicate = LearningResource::query()
            ->where('course_id', $course->id)
            ->where('title', $validated['title'])
            ->first();

        if ($duplicate !== null) {
            return response()->json([
                'message' => 'A learning resource with this title already exists for the course.',
                ...$this->resourcePayload($duplicate),
            ], 409);
        }

        $type = ResourceType::from($validated['type']);
        $normalized = $normalizer->normalize($validated, $type, $course->id);

        $resource = DB::transaction(function () use ($validated, $course, $type, $normalized, $externalId): LearningResource {
            return LearningResource::query()->create([
                'title' => $validated['title'],
                'slug' => $this->uniqueSlug($validated['title']),
                'external_id' => $externalId,
                'description' => $validated['description'] ?? null,
                'type' => $type,
                'course_id' => $course->id,
                'stream_id' => $course->stream_id,
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_published' => (bool) ($validated['is_published'] ?? false),
                'is_premium' => (bool) ($validated['is_premium'] ?? false),
                'generation_kind' => $normalized['generation_kind'],
                'content' => $normalized['content'],
                'files' => [],
                'telegram_files' => [],
            ]);
        });

        return response()->json($this->resourcePayload($resource), 201);
    }

    public function update(Request $request, LearningResource $learning): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_published' => ['sometimes', 'boolean'],
            'is_premium' => ['sometimes', 'boolean'],
            'time_limit_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
        ]);

        DB::transaction(function () use ($learning, $validated): void {
            if (array_key_exists('title', $validated)) {
                $learning->title = $validated['title'];
            }

            if (array_key_exists('description', $validated)) {
                $learning->description = $validated['description'];
            }

            if (array_key_exists('sort_order', $validated)) {
                $learning->sort_order = $validated['sort_order'];
            }

            if (array_key_exists('is_published', $validated)) {
                $learning->is_published = $validated['is_published'];
            }

            if (array_key_exists('is_premium', $validated)) {
                $learning->is_premium = $validated['is_premium'];
            }

            if (array_key_exists('time_limit_minutes', $validated)) {
                $content = is_array($learning->content) ? $learning->content : [];
                $payload = is_array($content['payload'] ?? null) ? $content['payload'] : [];

                if ($validated['time_limit_minutes'] === null) {
                    unset($payload['timeLimitMinutes']);
                } else {
                    $payload['timeLimitMinutes'] = (int) $validated['time_limit_minutes'];
                }

                $content['payload'] = $payload;
                $learning->content = $content;
            }

            $learning->save();
        });

        return response()->json($this->resourcePayload($learning->fresh()));
    }

    /**
     * @return array{id: int, slug: string, url: string}
     */
    protected function resourcePayload(LearningResource $resource): array
    {
        return [
            'id' => $resource->id,
            'slug' => $resource->slug,
            'url' => LearningFilamentResource::getUrl('edit', ['record' => $resource]),
        ];
    }

    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'learning';
        }

        $slug = $base;
        $suffix = 2;

        while (LearningResource::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
