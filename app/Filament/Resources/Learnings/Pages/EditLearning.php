<?php

namespace App\Filament\Resources\Learnings\Pages;

use App\Filament\Resources\Learnings\LearningResource;
use App\Filament\Resources\TelegramFileAssets\TelegramFileAssetResource;
use App\Support\LearningResourceFiles;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditLearning extends EditRecord
{
    protected static string $resource = LearningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('telegramVault')
                ->label('Telegram vault')
                ->icon(Heroicon::OutlinedPaperClip)
                ->url(TelegramFileAssetResource::getUrl())
                ->openUrlInNewTab()
                ->color('gray'),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (is_string($data['content'] ?? null)) {
            $decoded = json_decode($data['content'], true);
            $data['content'] = is_array($decoded) ? $decoded : null;
        }

        return LearningResourceFiles::normalizeFormFiles($data);
    }
}
