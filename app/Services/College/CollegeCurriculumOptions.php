<?php

namespace App\Services\College;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class CollegeCurriculumOptions
{
    public function __construct(private CollegeApiClient $client) {}

    /**
     * @return array<string, string>
     */
    public function courseOptions(?string $stream = null): array
    {
        return $this->safely(function () use ($stream): array {
            $options = [];

            foreach ($this->client->listCourses($stream) as $course) {
                $id = $this->id($course);
                if ($id === null) {
                    continue;
                }

                $code = $course['courseCode'] ?? $course['code'] ?? null;
                $name = (string) ($course['name'] ?? $course['title'] ?? $code ?? $id);
                $stream = $course['stream'] ?? null;

                $label = filled($code) && $name !== $code
                    ? $code.' — '.$name
                    : $name;

                if (filled($stream)) {
                    $label .= ' ('.$stream.')';
                }

                $options[$id] = $label;
            }

            return $options;
        }, 'courses');
    }

    /**
     * @return array<string, string>
     */
    public function moduleOptions(string $courseId): array
    {
        return $this->safely(function () use ($courseId): array {
            $options = [];

            foreach ($this->client->listModules($courseId) as $module) {
                $id = $this->id($module);
                if ($id === null) {
                    continue;
                }

                $number = $module['moduleNumber'] ?? null;
                $name = (string) ($module['name'] ?? $module['title'] ?? $id);
                $options[$id] = filled($number) ? "Module {$number}: {$name}" : $name;
            }

            return $options;
        }, 'modules');
    }

    /**
     * @return array<string, string>
     */
    public function unitOptions(string $moduleId): array
    {
        return $this->safely(function () use ($moduleId): array {
            $options = [];
            $curriculum = $this->client->curriculum($moduleId);
            $units = $curriculum['units'] ?? [];

            foreach (is_array($units) ? $units : [] as $unit) {
                if (! is_array($unit)) {
                    continue;
                }

                $id = $this->id($unit);
                if ($id === null) {
                    continue;
                }

                $number = $unit['unitNumber'] ?? $unit['number'] ?? null;
                $name = (string) ($unit['name'] ?? $unit['title'] ?? $id);
                $options[$id] = filled($number) ? "Unit {$number}: {$name}" : $name;
            }

            return $options;
        }, 'units');
    }

    /**
     * @return array<string, string>
     */
    public function sectionOptions(string $moduleId, ?string $unitId = null): array
    {
        return $this->safely(function () use ($moduleId, $unitId): array {
            $options = [];
            $curriculum = $this->client->curriculum($moduleId);
            $units = $curriculum['units'] ?? [];

            foreach (is_array($units) ? $units : [] as $unit) {
                if (! is_array($unit)) {
                    continue;
                }

                $currentUnitId = $this->id($unit);
                if ($unitId !== null && $currentUnitId !== $unitId) {
                    continue;
                }

                $sections = $unit['sections'] ?? $unit['childSections'] ?? [];
                foreach (is_array($sections) ? $sections : [] as $section) {
                    if (! is_array($section)) {
                        continue;
                    }

                    $id = $this->id($section);
                    if ($id === null) {
                        continue;
                    }

                    $number = $section['sectionNumber'] ?? $section['number'] ?? null;
                    $name = (string) ($section['name'] ?? $section['title'] ?? $id);
                    $options[$id] = filled($number) ? "Section {$number}: {$name}" : $name;
                }
            }

            if ($unitId === null) {
                $standalone = $curriculum['standaloneSections'] ?? $curriculum['sections'] ?? [];
                foreach (is_array($standalone) ? $standalone : [] as $section) {
                    if (! is_array($section)) {
                        continue;
                    }

                    $id = $this->id($section);
                    if ($id === null || isset($options[$id])) {
                        continue;
                    }

                    $name = (string) ($section['name'] ?? $section['title'] ?? $id);
                    $options[$id] = $name;
                }
            }

            return $options;
        }, 'sections');
    }

    public function labelFor(string $moduleId, string $scopeType, string $scopeId): string
    {
        $options = match ($scopeType) {
            'sectionId' => $this->sectionOptions($moduleId),
            'unitId' => $this->unitOptions($moduleId),
            default => $this->moduleOptionsFromCurriculum($moduleId),
        };

        return $options[$scopeId] ?? $scopeId;
    }

    /**
     * @param  callable(): array<string, string>  $callback
     * @return array<string, string>
     */
    protected function safely(callable $callback, string $context): array
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            Log::warning('Failed to load Noviq college curriculum options.', [
                'context' => $context,
                'message' => $exception->getMessage(),
            ]);

            Notification::make()
                ->title('Could not load Noviq college '.$context)
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    protected function moduleOptionsFromCurriculum(string $moduleId): array
    {
        return $this->safely(function () use ($moduleId): array {
            $curriculum = $this->client->curriculum($moduleId);
            $module = $curriculum['module'] ?? null;
            $name = is_array($module)
                ? (string) ($module['name'] ?? $module['title'] ?? $moduleId)
                : (string) ($curriculum['name'] ?? $curriculum['title'] ?? $moduleId);

            return [$moduleId => $name];
        }, 'module');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function id(array $item): ?string
    {
        $id = $item['_id'] ?? $item['id'] ?? null;

        return filled($id) ? (string) $id : null;
    }
}
