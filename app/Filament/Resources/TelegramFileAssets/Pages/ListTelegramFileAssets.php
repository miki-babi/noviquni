<?php

namespace App\Filament\Resources\TelegramFileAssets\Pages;

use App\Filament\Resources\TelegramFileAssets\TelegramFileAssetResource;
use Filament\Resources\Pages\ListRecords;

class ListTelegramFileAssets extends ListRecords
{
    protected static string $resource = TelegramFileAssetResource::class;

    public function getSubheading(): ?string
    {
        return 'DM a document to the bot as TELEGRAM_FILE_ADMIN_USERNAME to store a file_id, then attach it on a learning resource.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
