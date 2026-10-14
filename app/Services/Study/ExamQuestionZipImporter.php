<?php

namespace App\Services\Study;

use App\Support\StudyRichText;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use ZipArchive;

class ExamQuestionZipImporter
{
    private const array AllowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * @return array{
     *     questions: list<array{question: array<string, mixed>, options: list<array{body: array<string, mixed>}>, answerIndex: int, explanation: array<string, mixed>}>,
     *     title: ?string,
     *     instructions: ?string,
     *     timeLimitMinutes: ?int,
     *     errors: list<string>,
     * }
     */
    public function import(string $zipPath, int|string|null $courseId): array
    {
        if (! is_file($zipPath)) {
            throw new InvalidArgumentException('The uploaded ZIP file could not be found.');
        }

        $extractPath = storage_path('app/tmp/exam-imports/'.Str::uuid()->toString());
        File::ensureDirectoryExists($extractPath);

        try {
            $this->extractZip($zipPath, $extractPath);

            $manifestPath = $this->findQuestionsJson($extractPath);

            if ($manifestPath === null) {
                return $this->emptyResult(['questions.json was not found in the ZIP.']);
            }

            $decoded = json_decode((string) file_get_contents($manifestPath), true);

            if (! is_array($decoded)) {
                return $this->emptyResult(['questions.json is not valid JSON.']);
            }

            $rawQuestions = $decoded['questions'] ?? null;

            if (! is_array($rawQuestions) || $rawQuestions === []) {
                return $this->emptyResult(['questions.json must contain a non-empty "questions" array.']);
            }

            $imagesRoot = $this->resolveImagesRoot($extractPath, $manifestPath);
            $errors = [];
            $questions = [];

            foreach (array_values($rawQuestions) as $index => $rawQuestion) {
                $row = $index + 1;

                try {
                    $questions[] = $this->parseQuestion($rawQuestion, $row, $imagesRoot, $courseId);
                } catch (InvalidArgumentException $exception) {
                    $errors[] = $exception->getMessage();
                }
            }

            if ($questions === []) {
                return $this->emptyResult(
                    $errors !== [] ? $errors : ['No valid questions were imported.']
                );
            }

            $timeLimit = $decoded['timeLimitMinutes'] ?? null;

            return [
                'questions' => $questions,
                'title' => filled($decoded['title'] ?? null) ? (string) $decoded['title'] : null,
                'instructions' => filled($decoded['instructions'] ?? null) ? (string) $decoded['instructions'] : null,
                'timeLimitMinutes' => is_numeric($timeLimit) ? max(1, (int) $timeLimit) : null,
                'errors' => $errors,
            ];
        } finally {
            File::deleteDirectory($extractPath);
        }
    }

    /**
     * Build a sample ZIP bytes string for download.
     */
    public function sampleZipContents(): string
    {
        $tempDir = storage_path('app/tmp/exam-import-sample-'.Str::uuid()->toString());
        $imagesDir = $tempDir.DIRECTORY_SEPARATOR.'images';
        File::ensureDirectoryExists($imagesDir);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        file_put_contents($imagesDir.DIRECTORY_SEPARATOR.'q1.png', $png ?: '');
        file_put_contents($imagesDir.DIRECTORY_SEPARATOR.'choice-b.png', $png ?: '');
        file_put_contents($imagesDir.DIRECTORY_SEPARATOR.'explain-1.png', $png ?: '');

        $manifest = [
            'title' => 'Sample exam',
            'instructions' => 'Choose the best answer for each question.',
            'timeLimitMinutes' => 30,
            'questions' => [
                [
                    'question' => [
                        'text' => 'Which diagram is correct?',
                        'images' => ['q1.png'],
                    ],
                    'options' => [
                        ['text' => 'Option A'],
                        ['text' => 'Option B', 'images' => ['choice-b.png']],
                        ['text' => 'Option C'],
                        ['text' => 'Option D'],
                    ],
                    'answerIndex' => 1,
                    'explanation' => [
                        'text' => 'B matches the figure.',
                        'images' => ['explain-1.png'],
                    ],
                ],
                [
                    'question' => 'Plain-text question without images?',
                    'options' => ['Yes', 'No'],
                    'answerIndex' => 0,
                    'explanation' => 'Shorthand string fields are supported.',
                ],
            ],
        ];

        file_put_contents(
            $tempDir.DIRECTORY_SEPARATOR.'questions.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $zipPath = $tempDir.'.zip';

        try {
            $zip = new ZipArchive;

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create the sample ZIP.');
            }

            $zip->addFile($tempDir.DIRECTORY_SEPARATOR.'questions.json', 'questions.json');
            $zip->addFile($imagesDir.DIRECTORY_SEPARATOR.'q1.png', 'images/q1.png');
            $zip->addFile($imagesDir.DIRECTORY_SEPARATOR.'choice-b.png', 'images/choice-b.png');
            $zip->addFile($imagesDir.DIRECTORY_SEPARATOR.'explain-1.png', 'images/explain-1.png');
            $zip->close();

            return (string) file_get_contents($zipPath);
        } finally {
            File::deleteDirectory($tempDir);

            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
        }
    }

    protected function extractZip(string $zipPath, string $extractPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new InvalidArgumentException('The uploaded file is not a valid ZIP archive.');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);

                if (! is_string($name) || $name === '' || str_ends_with($name, '/')) {
                    continue;
                }

                $normalized = str_replace('\\', '/', $name);

                if (str_contains($normalized, '..') || str_starts_with($normalized, '/')) {
                    throw new InvalidArgumentException('The ZIP contains an unsafe file path: '.$normalized);
                }

                $target = $extractPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalized);
                File::ensureDirectoryExists(dirname($target));

                $stream = $zip->getStream($name);

                if ($stream === false) {
                    continue;
                }

                file_put_contents($target, stream_get_contents($stream) ?: '');
                fclose($stream);
            }
        } finally {
            $zip->close();
        }
    }

    protected function findQuestionsJson(string $extractPath): ?string
    {
        $direct = $extractPath.DIRECTORY_SEPARATOR.'questions.json';

        if (is_file($direct)) {
            return $direct;
        }

        $matches = File::glob($extractPath.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'questions.json') ?: [];

        return $matches[0] ?? null;
    }

    protected function resolveImagesRoot(string $extractPath, string $manifestPath): string
    {
        $besideManifest = dirname($manifestPath).DIRECTORY_SEPARATOR.'images';

        if (is_dir($besideManifest)) {
            return $besideManifest;
        }

        $atRoot = $extractPath.DIRECTORY_SEPARATOR.'images';

        if (is_dir($atRoot)) {
            return $atRoot;
        }

        return $besideManifest;
    }

    /**
     * @return array{question: array<string, mixed>, options: list<array{body: array<string, mixed>}>, answerIndex: int, explanation: array<string, mixed>}
     */
    protected function parseQuestion(mixed $rawQuestion, int $row, string $imagesRoot, int|string|null $courseId): array
    {
        if (! is_array($rawQuestion)) {
            throw new InvalidArgumentException("Question {$row}: must be an object.");
        }

        $questionDoc = $this->fieldToDocument($rawQuestion['question'] ?? null, $row, 'question', $imagesRoot, $courseId);

        $rawOptions = $rawQuestion['options'] ?? null;

        if (! is_array($rawOptions)) {
            throw new InvalidArgumentException("Question {$row}: options must be an array.");
        }

        $options = [];

        foreach (array_values($rawOptions) as $optionIndex => $rawOption) {
            $options[] = [
                'body' => $this->fieldToDocument(
                    $rawOption,
                    $row,
                    'option '.chr(65 + $optionIndex),
                    $imagesRoot,
                    $courseId,
                ),
            ];
        }

        if (count($options) < 2) {
            throw new InvalidArgumentException("Question {$row}: needs at least 2 options.");
        }

        if (StudyRichText::isEmpty($questionDoc)) {
            throw new InvalidArgumentException("Question {$row}: question text/images are empty.");
        }

        foreach ($options as $optionIndex => $option) {
            if (StudyRichText::isEmpty($option['body'])) {
                throw new InvalidArgumentException(
                    "Question {$row}: option ".chr(65 + $optionIndex).' is empty.'
                );
            }
        }

        $answerIndex = (int) ($rawQuestion['answerIndex'] ?? 0);

        if ($answerIndex < 0 || $answerIndex >= count($options)) {
            throw new InvalidArgumentException(
                "Question {$row}: answerIndex must be between 0 and ".(count($options) - 1).'.'
            );
        }

        $explanationDoc = array_key_exists('explanation', $rawQuestion)
            ? $this->fieldToDocument($rawQuestion['explanation'], $row, 'explanation', $imagesRoot, $courseId)
            : StudyRichText::emptyDocument();

        return [
            'question' => $questionDoc,
            'options' => $options,
            'answerIndex' => $answerIndex,
            'explanation' => $explanationDoc,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fieldToDocument(
        mixed $field,
        int $row,
        string $label,
        string $imagesRoot,
        int|string|null $courseId,
    ): array {
        if ($field === null || $field === '') {
            return StudyRichText::emptyDocument();
        }

        if (is_string($field)) {
            return StudyRichText::forEditor($field);
        }

        if (! is_array($field)) {
            throw new InvalidArgumentException("Question {$row}: {$label} must be a string or object.");
        }

        // Already a TipTap document
        if (($field['type'] ?? null) === 'doc') {
            return $field;
        }

        $text = $field['text'] ?? '';
        $images = $field['images'] ?? [];

        if (! is_array($images)) {
            throw new InvalidArgumentException("Question {$row}: {$label}.images must be an array.");
        }

        $document = filled($text)
            ? StudyRichText::forEditor(is_string($text) ? $text : (string) $text)
            : StudyRichText::emptyDocument();

        $content = is_array($document['content'] ?? null) ? $document['content'] : [];

        // Drop empty trailing paragraph when we will append images only
        if ($content === [['type' => 'paragraph']] || $content === [['type' => 'paragraph', 'content' => []]]) {
            $content = [];
        }

        foreach ($images as $imageRef) {
            if (! is_string($imageRef) || blank($imageRef)) {
                throw new InvalidArgumentException("Question {$row}: {$label} has an invalid image reference.");
            }

            $storedPath = $this->storeImage($imageRef, $imagesRoot, $courseId, $row, $label);
            $content[] = $this->imageNode($storedPath, pathinfo($imageRef, PATHINFO_FILENAME));
        }

        if ($content === []) {
            return StudyRichText::emptyDocument();
        }

        return [
            'type' => 'doc',
            'content' => $content,
        ];
    }

    protected function storeImage(
        string $imageRef,
        string $imagesRoot,
        int|string|null $courseId,
        int $row,
        string $label,
    ): string {
        $relative = ltrim(str_replace('\\', '/', $imageRef), '/');

        if (str_starts_with($relative, 'images/')) {
            $relative = substr($relative, strlen('images/'));
        }

        if ($relative === '' || str_contains($relative, '..')) {
            throw new InvalidArgumentException("Question {$row}: {$label} image path is unsafe ({$imageRef}).");
        }

        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        if (! in_array($extension, self::AllowedImageExtensions, true)) {
            throw new InvalidArgumentException(
                "Question {$row}: {$label} image must be jpg, jpeg, png, webp, or gif ({$imageRef})."
            );
        }

        $source = $imagesRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (! is_file($source)) {
            throw new InvalidArgumentException("Question {$row}: {$label} image not found: {$imageRef}");
        }

        $directory = StudyRichText::attachmentsDirectory($courseId);
        $filename = Str::uuid()->toString().'.'.$extension;
        $destination = $directory.'/'.$filename;

        try {
            Storage::disk(StudyRichText::AttachmentsDisk)->put(
                $destination,
                (string) file_get_contents($source),
            );
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                "Question {$row}: failed to store {$label} image {$imageRef}: ".$exception->getMessage()
            );
        }

        return $destination;
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

    /**
     * @param  list<string>  $errors
     * @return array{
     *     questions: list<array<string, mixed>>,
     *     title: null,
     *     instructions: null,
     *     timeLimitMinutes: null,
     *     errors: list<string>,
     * }
     */
    protected function emptyResult(array $errors): array
    {
        return [
            'questions' => [],
            'title' => null,
            'instructions' => null,
            'timeLimitMinutes' => null,
            'errors' => $errors,
        ];
    }
}
