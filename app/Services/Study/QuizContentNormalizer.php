<?php

namespace App\Services\Study;

use App\Enums\CollegeResourceKind;
use App\Enums\ResourceType;
use App\Support\LearningResourceFiles;
use App\Support\StudyRichText;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class QuizContentNormalizer
{
    private const array AllowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * @param  array<string, mixed>  $data
     * @return array{content: array<string, mixed>, generation_kind: CollegeResourceKind}
     */
    public function normalize(array $data, ResourceType $type, int|string|null $courseId): array
    {
        $kind = $type === ResourceType::PracticeExams
            ? CollegeResourceKind::Exam
            : CollegeResourceKind::Quiz;

        $rawContent = is_array($data['content'] ?? null) ? $data['content'] : [];
        $rawPayload = is_array($rawContent['payload'] ?? null) ? $rawContent['payload'] : [];
        $rawQuestions = is_array($rawPayload['questions'] ?? null) ? array_values($rawPayload['questions']) : [];

        if ($rawQuestions === []) {
            throw ValidationException::withMessages([
                'content.payload.questions' => ['At least one question is required.'],
            ]);
        }

        $questions = [];

        foreach ($rawQuestions as $index => $rawQuestion) {
            $questions[] = $this->normalizeQuestion($rawQuestion, $index, $courseId);
        }

        $payload = [
            'questions' => $questions,
        ];

        if ($type === ResourceType::PracticeExams) {
            if (filled($rawPayload['title'] ?? null)) {
                $payload['title'] = (string) $rawPayload['title'];
            }

            if (filled($rawPayload['instructions'] ?? null)) {
                $payload['instructions'] = (string) $rawPayload['instructions'];
            }
        }

        $minutes = $data['time_limit_minutes'] ?? $rawPayload['timeLimitMinutes'] ?? null;

        if (is_numeric($minutes) && (int) $minutes > 0) {
            $payload['timeLimitMinutes'] = (int) $minutes;
        }

        return [
            'generation_kind' => $kind,
            'content' => [
                'kind' => $kind->value,
                'payload' => $payload,
            ],
        ];
    }

    /**
     * @return array{question: array<string, mixed>, options: list<array<string, mixed>>, answerIndex: int, explanation?: array<string, mixed>}
     */
    protected function normalizeQuestion(mixed $rawQuestion, int $index, int|string|null $courseId): array
    {
        $row = $index + 1;

        if (! is_array($rawQuestion)) {
            throw ValidationException::withMessages([
                "content.payload.questions.{$index}" => ["Question {$row} must be an object."],
            ]);
        }

        $options = is_array($rawQuestion['options'] ?? null)
            ? array_values($rawQuestion['options'])
            : [];

        if (count($options) < 2 || count($options) > 6) {
            throw ValidationException::withMessages([
                "content.payload.questions.{$index}.options" => [
                    "Question {$row} must have between 2 and 6 options.",
                ],
            ]);
        }

        if (! array_key_exists('answerIndex', $rawQuestion) || ! is_int($rawQuestion['answerIndex'])) {
            if (is_numeric($rawQuestion['answerIndex'] ?? null)) {
                $rawQuestion['answerIndex'] = (int) $rawQuestion['answerIndex'];
            } else {
                throw ValidationException::withMessages([
                    "content.payload.questions.{$index}.answerIndex" => [
                        "Question {$row} requires an integer answerIndex.",
                    ],
                ]);
            }
        }

        $answerIndex = (int) $rawQuestion['answerIndex'];

        if ($answerIndex < 0 || $answerIndex >= count($options)) {
            throw ValidationException::withMessages([
                "content.payload.questions.{$index}.answerIndex" => [
                    "Question {$row} answerIndex is out of range.",
                ],
            ]);
        }

        $questionImages = is_array($rawQuestion['images'] ?? null)
            ? array_values($rawQuestion['images'])
            : [];

        $normalizedOptions = [];

        foreach ($options as $optionIndex => $option) {
            $normalizedOptions[] = $this->fieldToDocument(
                $option,
                $row,
                'option '.($optionIndex + 1),
                $courseId,
                "content.payload.questions.{$index}.options.{$optionIndex}",
            );
        }

        $item = [
            'question' => $this->fieldToDocument(
                [
                    'text' => $rawQuestion['question'] ?? '',
                    'images' => $questionImages,
                ],
                $row,
                'question',
                $courseId,
                "content.payload.questions.{$index}.question",
            ),
            'options' => $normalizedOptions,
            'answerIndex' => $answerIndex,
        ];

        if (! StudyRichText::isEmpty($rawQuestion['explanation'] ?? null)) {
            $item['explanation'] = $this->fieldToDocument(
                $rawQuestion['explanation'],
                $row,
                'explanation',
                $courseId,
                "content.payload.questions.{$index}.explanation",
            );
        }

        if (StudyRichText::isEmpty($item['question'])) {
            throw ValidationException::withMessages([
                "content.payload.questions.{$index}.question" => [
                    "Question {$row} text is required.",
                ],
            ]);
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fieldToDocument(
        mixed $field,
        int $row,
        string $label,
        int|string|null $courseId,
        string $errorKey,
    ): array {
        if ($field === null || $field === '') {
            return StudyRichText::emptyDocument();
        }

        if (is_string($field)) {
            return StudyRichText::forEditor($field);
        }

        if (! is_array($field)) {
            throw ValidationException::withMessages([
                $errorKey => ["Question {$row}: {$label} must be a string or object."],
            ]);
        }

        if (($field['type'] ?? null) === 'doc') {
            return $field;
        }

        $text = $field['text'] ?? ($field['question'] ?? '');
        $images = $field['images'] ?? [];

        if (! is_array($images)) {
            throw ValidationException::withMessages([
                $errorKey => ["Question {$row}: {$label}.images must be an array."],
            ]);
        }

        $document = filled($text)
            ? StudyRichText::forEditor(is_string($text) ? $text : (string) $text)
            : StudyRichText::emptyDocument();

        $content = is_array($document['content'] ?? null) ? $document['content'] : [];

        if ($content === [['type' => 'paragraph']] || $content === [['type' => 'paragraph', 'content' => []]]) {
            $content = [];
        }

        foreach ($images as $imageRef) {
            if (! is_string($imageRef) || blank($imageRef)) {
                throw ValidationException::withMessages([
                    $errorKey => ["Question {$row}: {$label} has an invalid image reference."],
                ]);
            }

            $storedPath = $this->resolveAndStoreImage($imageRef, $courseId, $row, $label, $errorKey);
            $content[] = $this->imageNode($storedPath, pathinfo(parse_url($imageRef, PHP_URL_PATH) ?: $imageRef, PATHINFO_FILENAME) ?: 'image');
        }

        if ($content === []) {
            return StudyRichText::emptyDocument();
        }

        return [
            'type' => 'doc',
            'content' => $content,
        ];
    }

    protected function resolveAndStoreImage(
        string $imageRef,
        int|string|null $courseId,
        int $row,
        string $label,
        string $errorKey,
    ): string {
        $path = $this->existingStoragePath($imageRef);

        if ($path !== null) {
            if (! Storage::disk(StudyRichText::AttachmentsDisk)->exists($path)) {
                throw ValidationException::withMessages([
                    $errorKey => ["Question {$row}: {$label} image was not found on disk ({$imageRef})."],
                ]);
            }

            return $path;
        }

        if (! str_starts_with($imageRef, 'http://') && ! str_starts_with($imageRef, 'https://')) {
            throw ValidationException::withMessages([
                $errorKey => [
                    "Question {$row}: {$label} image must be an upload path, storage URL, or http(s) URL.",
                ],
            ]);
        }

        $extension = strtolower(pathinfo(parse_url($imageRef, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        if (! in_array($extension, self::AllowedImageExtensions, true)) {
            $extension = 'png';
        }

        try {
            $response = Http::timeout(20)->get($imageRef);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                $errorKey => [
                    "Question {$row}: failed to download {$label} image: ".$exception->getMessage(),
                ],
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                $errorKey => ["Question {$row}: failed to download {$label} image ({$imageRef})."],
            ]);
        }

        $directory = StudyRichText::attachmentsDirectory($courseId);
        $destination = $directory.'/'.Str::uuid()->toString().'.'.$extension;

        Storage::disk(StudyRichText::AttachmentsDisk)->put($destination, $response->body());

        return $destination;
    }

    protected function existingStoragePath(string $imageRef): ?string
    {
        $prefix = LearningResourceFiles::DirectoryPrefix;
        $normalized = str_replace('\\', '/', $imageRef);

        if (str_starts_with($normalized, $prefix.'/')) {
            return ltrim($normalized, '/');
        }

        $path = parse_url($normalized, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        foreach (['/storage/', 'storage/'] as $marker) {
            $pos = strpos($path, $marker);

            if ($pos === false) {
                continue;
            }

            $relative = ltrim(substr($path, $pos + strlen($marker)), '/');

            if (str_starts_with($relative, $prefix.'/')) {
                return $relative;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function imageNode(string $storedPath, string $alt): array
    {
        return [
            'type' => 'image',
            'attrs' => [
                'id' => $storedPath,
                'src' => null,
                'alt' => $alt,
                'title' => null,
                'width' => null,
                'height' => null,
            ],
        ];
    }
}
