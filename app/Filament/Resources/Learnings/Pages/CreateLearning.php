<?php

namespace App\Filament\Resources\Learnings\Pages;

use App\Filament\Resources\Learnings\LearningResource;
use App\Filament\Resources\Learnings\Schemas\LearningForm;
use App\Support\LearningResourceFiles;
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
