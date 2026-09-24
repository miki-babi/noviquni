<?php

namespace App\Filament\Resources\TelegramCommands\Pages;

use App\Filament\Resources\TelegramCommands\TelegramCommandResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelegramCommands extends ListRecords
{
    protected static string $resource = TelegramCommandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
