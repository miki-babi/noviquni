<?php

namespace App\Filament\Resources\Learnings\Pages;

use App\Filament\Resources\Learnings\LearningResource;
use App\Filament\Resources\Learnings\Schemas\LearningForm;
use App\Models\Course;
use App\Models\Stream;
use App\Models\TelegramFileAsset;
use App\Support\LearningResourceFiles;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Str;

class CreateLearning extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = LearningResource::class;

    /**
     * @param  array{
     *     course_id?: int|string|null,
     *     stream_id?: int|string|null,
     *     telegram_file_ids?: list<string>|string|null,
     *     existing_files?: list<string>|string|null,
     *     title?: string|null,
     * }  $parameters
     */
    public static function getCreateUrl(array $parameters = []): string
    {
        $query = [];

        if (filled($parameters['course_id'] ?? null)) {
            $query['course_id'] = $parameters['course_id'];
        }

        if (filled($parameters['stream_id'] ?? null)) {
            $query['stream_id'] = $parameters['stream_id'];
        }

        $fileIds = $parameters['telegram_file_ids'] ?? null;

        if (is_array($fileIds)) {
            $fileIds = implode(',', array_values(array_filter($fileIds)));
        }

        if (filled($fileIds)) {
            $query['telegram_file_ids'] = $fileIds;
        }

        $existingFiles = $parameters['existing_files'] ?? null;

        if (is_array($existingFiles)) {
            $existingFiles = implode(',', array_values(array_filter($existingFiles)));
        }

        if (filled($existingFiles)) {
            $query['existing_files'] = $existingFiles;
        }

        if (filled($parameters['title'] ?? null)) {
            $query['title'] = $parameters['title'];
        }

        return LearningResource::getUrl('create', $query);
    }

    /**
     * @return array<Step>
     */
    protected function getSteps(): array
    {
        return LearningForm::createSteps();
    }

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill($this->prefillDataFromQuery());

        $this->callHook('afterFill');
    }

    /**
     * @return array<string, mixed>
     */
    protected function prefillDataFromQuery(): array
    {
        $data = [];

        $courseId = request()->query('course_id');
        $course = null;

        if (filled($courseId) && is_numeric($courseId)) {
            $course = Course::query()->find((int) $courseId);

            if ($course !== null) {
                $data['course_id'] = $course->id;
                $data['stream_id'] = $course->stream_id;
            }
        }

        if ($course === null) {
            $streamId = request()->query('stream_id');

            if (filled($streamId) && is_numeric($streamId) && Stream::query()->whereKey((int) $streamId)->exists()) {
                $data['stream_id'] = (int) $streamId;
            }
        }

        $rawFileIds = request()->query('telegram_file_ids');

        if (is_string($rawFileIds) && filled($rawFileIds)) {
            $requestedIds = collect(explode(',', $rawFileIds))
                ->map(fn (string $id): string => trim($id))
                ->filter()
                ->unique()
                ->take(10)
                ->values()
                ->all();

            if ($requestedIds !== []) {
                $validIds = TelegramFileAsset::query()
                    ->whereIn('file_id', $requestedIds)
                    ->pluck('file_id')
                    ->all();

                $orderedValid = array_values(array_filter(
                    $requestedIds,
                    fn (string $id): bool => in_array($id, $validIds, true),
                ));

                if ($orderedValid !== []) {
                    $data['file_source'] = 'telegram';
                    $data['file_mode'] = count($orderedValid) === 1 ? 'single' : 'multiple';
                    $data['telegram_file_ids'] = $orderedValid;
                }
            }
        }

        $rawExistingFiles = request()->query('existing_files');
        $courseIdForFiles = $data['course_id'] ?? null;

        if (is_string($rawExistingFiles) && filled($rawExistingFiles) && filled($courseIdForFiles)) {
            $requestedPaths = collect(explode(',', $rawExistingFiles))
                ->map(fn (string $path): string => trim($path))
                ->filter()
                ->unique()
                ->take(10)
                ->values()
                ->all();

            $validPaths = array_values(array_filter(
                $requestedPaths,
                fn (string $path): bool => LearningResourceFiles::isValidPathForCourse($path, $courseIdForFiles),
            ));

            if ($validPaths !== []) {
                $data['file_source'] = 'existing';
                $data['file_mode'] = count($validPaths) === 1 ? 'single' : 'multiple';
                $data['existing_files'] = $validPaths;
            }
        }

        $title = request()->query('title');

        if (is_string($title) && filled(trim($title))) {
            $title = trim($title);
            $data['title'] = $title;
            $data['slug'] = Str::slug($title);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (is_string($data['content'] ?? null)) {
            $decoded = json_decode($data['content'], true);
            $data['content'] = is_array($decoded) ? $decoded : null;
        }

        $data = LearningResourceFiles::normalizeFormFiles($data);

        $data['generation_kind'] = null;
        $data['content'] = null;

        return $data;
    }
}
