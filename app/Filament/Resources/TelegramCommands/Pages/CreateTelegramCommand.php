<?php

namespace App\Filament\Resources\TelegramCommands\Pages;

use App\Filament\Resources\TelegramCommands\TelegramCommandResource;
use App\Models\TelegramCommand;
use Filament\Resources\Pages\CreateRecord;

class CreateTelegramCommand extends CreateRecord
{
    protected static string $resource = TelegramCommandResource::class;

    /**
     * @var list<int>
     */
    protected array $fileAssetIds = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->fileAssetIds = $this->extractFileAssetIds($data['vault_files'] ?? []);
        unset($data['vault_files']);

        return $data;
    }

    protected function afterCreate(): void
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
