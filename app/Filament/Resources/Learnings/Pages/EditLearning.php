<?php

namespace App\Filament\Resources\Learnings\Pages;

use App\Filament\Resources\Learnings\LearningResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLearning extends EditRecord
{
    protected static string $resource = LearningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['file_mode']);

        if (is_string($data['content'] ?? null)) {
            $decoded = json_decode($data['content'], true);
            $data['content'] = is_array($decoded) ? $decoded : null;
        }

        if (array_key_exists('files', $data)) {
            $files = $data['files'];

            if (is_string($files) && filled($files)) {
                $data['files'] = [$files];
            } elseif (! is_array($files) || $files === []) {
                $data['files'] = null;
            } else {
                $data['files'] = array_values(array_filter($files, fn ($path): bool => filled($path)));
            }
        }

        return $data;
    }
}
