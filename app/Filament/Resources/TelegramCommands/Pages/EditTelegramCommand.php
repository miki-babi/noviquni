<?php

namespace App\Filament\Resources\TelegramCommands\Pages;

use App\Filament\Resources\TelegramCommands\TelegramCommandResource;
use App\Models\TelegramCommand;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTelegramCommand extends EditRecord
{
    protected static string $resource = TelegramCommandResource::class;

    /**
     * @var list<int>
     */
    protected array $fileAssetIds = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var TelegramCommand $record */
        $record = $this->getRecord();

        $data['vault_files'] = $record->fileAssets
            ->map(fn ($asset): array => [
                'telegram_file_asset_id' => $asset->id,
            ])
            ->values()
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->fileAssetIds = $this->extractFileAssetIds($data['vault_files'] ?? []);
        unset($data['vault_files']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var TelegramCommand $record */
        $record = $this->record;
        $record->syncFileAssets($this->fileAssetIds);
    }

    /**
     * @return list<int>
     */
    protected function extractFileAssetIds(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)
            ->pluck('telegram_file_asset_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
