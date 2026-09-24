<?php

namespace App\Filament\Resources\TelegramFileAssets\Pages;

use App\Filament\Resources\TelegramFileAssets\TelegramFileAssetResource;
use Filament\Resources\Pages\ListRecords;

class ListTelegramFileAssets extends ListRecords
{
    protected static string $resource = TelegramFileAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
