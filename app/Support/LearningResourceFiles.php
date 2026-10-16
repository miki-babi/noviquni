<?php

namespace App\Support;

use App\Models\TelegramFileAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class LearningResourceFiles
{
    public const string DirectoryPrefix = 'learning-resources';

    public const int MaxSizeKilobytes = 102400; // 100MB

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
            'application/zip',
            'application/x-zip-compressed',
            'text/plain',
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
        return ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'txt', 'jpg', 'jpeg', 'png', 'webp'];
    }

    public static function directoryForCourse(int|string $courseId): string
    {
        return self::DirectoryPrefix.'/'.$courseId;
    }

    public static function diskName(): string
    {
        return 'public';
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

    /**
     * @return array<string, array{path: string, name: string, size: int|null, size_label: string}>
     */
    public static function listForCourse(int|string|null $courseId): array
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
            ->mapWithKeys(function (string $path) use ($disk): array {
                $size = $disk->size($path);

                return [
                    $path => [
                        'path' => $path,
                        'name' => basename($path),
                        'size' => $size,
                        'size_label' => self::formatBytes($size),
                    ],
                ];
            })
            ->all();
    }

    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }

    public static function hasAcceptedExtension(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, self::acceptedExtensions(), true);
    }

    public static function isPdfPath(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    public static function isPdfFileName(?string $name): bool
    {
        return filled($name) && self::isPdfPath($name);
    }

    /**
     * Probe public + local disks for a stored learning-resource key.
     *
     * @return array{
     *     public_root: string|null,
     *     local_root: string|null,
     *     public_exists: bool,
     *     local_exists: bool,
     *     absolute_public_path: string|null,
     *     absolute_local_path: string|null,
     *     public_dir_exists: bool,
     *     local_dir_exists: bool,
     *     public_sibling_count: int,
     *     local_sibling_count: int,
     * }
     */
    public static function probeDiskKey(string $key): array
    {
        $publicRoot = self::diskRoot('public');
        $localRoot = self::diskRoot('local');
        $dir = dirname($key);
        $dir = ($dir === '.' || $dir === '') ? null : $dir;

        $publicExists = Storage::disk('public')->exists($key);
        $localExists = Storage::disk('local')->exists($key);

        return [
            'public_root' => $publicRoot,
            'local_root' => $localRoot,
            'public_exists' => $publicExists,
            'local_exists' => $localExists,
            'absolute_public_path' => self::absoluteDiskPath('public', $key),
            'absolute_local_path' => self::absoluteDiskPath('local', $key),
            'public_dir_exists' => $dir !== null && Storage::disk('public')->directoryExists($dir),
            'local_dir_exists' => $dir !== null && Storage::disk('local')->directoryExists($dir),
            'public_sibling_count' => self::siblingFileCount('public', $dir),
            'local_sibling_count' => self::siblingFileCount('local', $dir),
        ];
    }

    /**
     * @param  list<string>|null  $paths
     */
    public static function formatDiskPathStatusLines(?array $paths): string
    {
        if (! is_array($paths) || $paths === []) {
            return '';
        }

        $lines = [];

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $public = Storage::disk('public')->exists($path) ? 'yes' : 'no';
            $local = Storage::disk('local')->exists($path) ? 'yes' : 'no';
            $lines[] = "{$path}  (public: {$public}, local: {$local})";
        }

        return implode("\n", $lines);
    }

    public static function diskRoot(string $disk): ?string
    {
        $root = config("filesystems.disks.{$disk}.root");

        return is_string($root) && $root !== '' ? $root : null;
    }

    public static function absoluteDiskPath(string $disk, string $key): ?string
    {
        try {
            return Storage::disk($disk)->path($key);
        } catch (Throwable) {
            return null;
        }
    }

    public static function siblingFileCount(string $disk, ?string $directory): int
    {
        if ($directory === null || $directory === '' || $directory === '.') {
            return 0;
        }

        try {
            if (! Storage::disk($disk)->directoryExists($directory)) {
                return 0;
            }

            return count(Storage::disk($disk)->files($directory));
        } catch (Throwable) {
            return 0;
        }
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

        $fileSource = $data['file_source'] ?? null;

        if (array_key_exists('telegram_file_ids', $data) || $fileSource === 'telegram') {
            $data['telegram_files'] = self::normalizeTelegramFileIds(
                $data['telegram_file_ids'] ?? [],
            );
        }

        unset($data['existing_files'], $data['file_source'], $data['file_mode'], $data['telegram_file_ids']);

        if ($fileSource === 'telegram') {
            $data['files'] = null;
        } elseif (in_array($fileSource, ['upload', 'existing'], true)) {
            $data['telegram_files'] = null;
        }

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
     * @return list<array{file_id: string, file_name: string|null}>|null
     */
    public static function normalizeTelegramFileIds(mixed $fileIds): ?array
    {
        if (is_string($fileIds) && filled($fileIds)) {
            $fileIds = [$fileIds];
        }

        if (! is_array($fileIds) || $fileIds === []) {
            return null;
        }

        $ids = array_values(array_filter($fileIds, fn ($id): bool => is_string($id) && filled($id)));

        if ($ids === []) {
            return null;
        }

        $assets = TelegramFileAsset::query()
            ->whereIn('file_id', $ids)
            ->get()
            ->keyBy('file_id');

        $normalized = [];

        foreach ($ids as $id) {
            $asset = $assets->get($id);

            $normalized[] = [
                'file_id' => $id,
                'file_name' => $asset?->file_name,
            ];
        }

        return $normalized;
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
