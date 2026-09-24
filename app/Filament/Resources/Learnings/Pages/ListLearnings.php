<?php

namespace App\Filament\Resources\Learnings\Pages;

use App\Filament\Pages\ManageCourseFiles;
use App\Filament\Resources\Learnings\LearningResource;
use App\Filament\Resources\TelegramFileAssets\TelegramFileAssetResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListLearnings extends ListRecords
{
    protected static string $resource = LearningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('telegramVault')
                ->label('Telegram vault')
                ->icon(Heroicon::OutlinedPaperClip)
                ->url(TelegramFileAssetResource::getUrl())
                ->color('gray'),
            Action::make('courseFiles')
                ->label('Course files')
                ->icon(Heroicon::OutlinedFolderOpen)
                ->url(ManageCourseFiles::getUrl())
                ->color('gray'),
            CreateAction::make(),
        ];
    }
}
