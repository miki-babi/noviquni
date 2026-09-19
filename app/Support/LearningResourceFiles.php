<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LearningResourceFiles
{
    public const string DirectoryPrefix = 'learning-resources';

    /**
     * @return list<string>
     */
    public static function acceptedMimeTypes(): array
    {
        return [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg',
            'image/png',
            'image/webp',
        ];
    }

    /**
     * @return list<string>
     */
    public static function acceptedExtensions(): array
    {
        return ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp'];
    }

    public static function directoryForCourse(int|string $courseId): string
    {
        return self::DirectoryPrefix.'/'.$courseId;
    }

    public static function diskName(): string
    {
        return (string) config('filesystems.default');
    }

    /**
     * @return array<string, string>
     */
    public static function optionsForCourse(int|string|null $courseId): array
    {
        if (! filled($courseId)) {
            return [];
        }

        $disk = Storage::disk(self::diskName());
        $directory = self::directoryForCourse($courseId);

        if (! $disk->exists($directory)) {
            return [];
        }

        return collect($disk->files($directory))
            ->filter(fn (string $path): bool => self::hasAcceptedExtension($path))
            ->sort()
            ->mapWithKeys(fn (string $path): array => [$path => basename($path)])
            ->all();
    }

    public static function countForCourse(int|string|null $courseId): int
    {
        return count(self::optionsForCourse($courseId));
    }

    public static function hasAcceptedExtension(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, self::acceptedExtensions(), true);
    }

    public static function isValidPathForCourse(string $path, int|string $courseId): bool
    {
        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        if (! str_starts_with($path, self::DirectoryPrefix.'/')) {
            return false;
        }

        $courseDirectory = self::directoryForCourse($courseId);

        if (preg_match('#^'.preg_quote(self::DirectoryPrefix, '#').'/(\d+)/#', $path, $matches) === 1
            && (string) $matches[1] !== (string) $courseId) {
            return false;
        }

        if (str_starts_with($path, $courseDirectory.'/') && ! self::hasAcceptedExtension($path)) {
            return false;
        }

        return Storage::disk(self::diskName())->exists($path);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFormFiles(array $data): array
    {
        if (array_key_exists('existing_files', $data)) {
            $data['files'] = $data['existing_files'];
        }

        unset($data['existing_files'], $data['file_source'], $data['file_mode']);

        if (! array_key_exists('files', $data)) {
            return $data;
        }

        $files = $data['files'];

        if (is_string($files) && filled($files)) {
            $data['files'] = [$files];
        } elseif (! is_array($files) || $files === []) {
            $data['files'] = null;
        } else {
            $data['files'] = array_values(array_filter($files, fn ($path): bool => filled($path)));
        }

        if (filled($data['files'] ?? null) && filled($data['course_id'] ?? null)) {
            self::assertPathsBelongToCourse($data['files'], $data['course_id']);
        }

        return $data;
    }

    /**
     * @param  list<string>  $paths
     */
    public static function assertPathsBelongToCourse(array $paths, int|string $courseId): void
    {
        foreach ($paths as $path) {
            if (! is_string($path) || ! self::isValidPathForCourse($path, $courseId)) {
                throw ValidationException::withMessages([
                    'files' => 'Each study file must exist in this course’s file library.',
                ]);
            }
        }
    }
}
