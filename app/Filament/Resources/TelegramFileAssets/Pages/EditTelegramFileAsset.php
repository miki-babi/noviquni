<?php

namespace App\Filament\Resources\TelegramFileAssets\Pages;

use App\Filament\Resources\TelegramFileAssets\TelegramFileAssetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTelegramFileAsset extends EditRecord
{
    protected static string $resource = TelegramFileAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
