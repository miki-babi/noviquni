<?php

namespace App\Filament\Resources\Learnings\Pages;

use App\Filament\Resources\Learnings\LearningResource;
use App\Filament\Resources\Learnings\Schemas\LearningForm;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;

class CreateLearning extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = LearningResource::class;

    /**
     * @return array<Step>
     */
    protected function getSteps(): array
    {
        return LearningForm::createSteps();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['creation_mode'], $data['file_mode']);

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

        if (blank($data['content'] ?? null) && filled($data['files'] ?? null)) {
            $data['generation_kind'] = null;
            $data['content'] = null;
        }

        return $data;
    }
}
