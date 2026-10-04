<?php

namespace App\Filament\Resources\Challenges\Pages;

use App\Enums\ChallengeStatus;
use App\Filament\Resources\Challenges\ChallengeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChallenge extends CreateRecord
{
    protected static string $resource = ChallengeResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] ??= ChallengeStatus::Draft->value;
        $data['created_by'] = auth()->id();
        $data['winners_count'] = 0;

        return $data;
    }
}
