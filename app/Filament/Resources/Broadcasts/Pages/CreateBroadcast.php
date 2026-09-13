<?php

namespace App\Filament\Resources\Broadcasts\Pages;

use App\Enums\BroadcastStatus;
use App\Filament\Resources\Broadcasts\BroadcastResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBroadcast extends CreateRecord
{
    protected static string $resource = BroadcastResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = ! empty($data['scheduled_at'])
            ? BroadcastStatus::Scheduled->value
            : BroadcastStatus::Draft->value;
        $data['created_by'] = auth()->id();

        return $data;
    }
}
