<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Actions\SendStudentTelegramMessageAction;
use App\Filament\Resources\Students\StudentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendStudentTelegramMessageAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->loadMissing('referrer');
    }
}
