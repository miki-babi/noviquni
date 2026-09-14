<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;

class ArtisanCommandRunner
{
    /**
     * Whitelisted Artisan commands only. Keys are labels; values are command + parameters.
     *
     * @return array<string, array{command: string, parameters: array<string, mixed>}>
     */
    public function allowed(): array
    {
        return [
            'about' => [
                'command' => 'about',
                'parameters' => [],
            ],
            'migrate --force' => [
                'command' => 'migrate',
                'parameters' => ['--force' => true],
            ],
            'migrate:status' => [
                'command' => 'migrate:status',
                'parameters' => [],
            ],
            'db:seed --force' => [
                'command' => 'db:seed',
                'parameters' => ['--force' => true],
            ],
            'optimize:clear' => [
                'command' => 'optimize:clear',
                'parameters' => [],
            ],
            'cache:clear' => [
                'command' => 'cache:clear',
                'parameters' => [],
            ],
            'config:clear' => [
                'command' => 'config:clear',
                'parameters' => [],
            ],
            'config:cache' => [
                'command' => 'config:cache',
                'parameters' => [],
            ],
            'route:clear' => [
                'command' => 'route:clear',
                'parameters' => [],
            ],
            'route:list' => [
                'command' => 'route:list',
                'parameters' => [],
            ],
            'view:clear' => [
                'command' => 'view:clear',
                'parameters' => [],
            ],
            'queue:failed' => [
                'command' => 'queue:failed',
                'parameters' => [],
            ],
            'queue:retry all' => [
                'command' => 'queue:retry',
                'parameters' => ['id' => 'all'],
            ],
            'filament:optimize-clear' => [
                'command' => 'filament:optimize-clear',
                'parameters' => [],
            ],
            'storage:link' => [
                'command' => 'storage:link',
                'parameters' => [],
            ],
        ];
    }

    /**
     * @return array{exit_code: int, output: string, command: string}
     */
    public function run(string $key): array
    {
        $allowed = $this->allowed();

        if (! array_key_exists($key, $allowed)) {
            throw new InvalidArgumentException('That Artisan command is not allowed.');
        }

        $definition = $allowed[$key];

        $exitCode = Artisan::call($definition['command'], $definition['parameters']);

        return [
            'exit_code' => $exitCode,
            'output' => Artisan::output(),
            'command' => $key,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return collect($this->allowed())
            ->mapWithKeys(fn (array $definition, string $key): array => [$key => $key])
            ->all();
    }
}
