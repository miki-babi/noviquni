<?php

namespace App\Filament\Resources\TelegramFileAssets;

use App\Filament\Resources\TelegramFileAssets\Pages\ListTelegramFileAssets;
use App\Filament\Resources\TelegramFileAssets\Tables\TelegramFileAssetsTable;
use App\Models\TelegramFileAsset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TelegramFileAssetResource extends Resource
{
    protected static ?string $model = TelegramFileAsset::class;

    protected static ?string $modelLabel = 'Telegram file';

    protected static ?string $pluralModelLabel = 'Telegram files';

    protected static ?string $navigationLabel = 'Telegram files';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperClip;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'file_name';

    public static function getNavigationBadge(): ?string
    {
        $count = TelegramFileAsset::query()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return TelegramFileAssetsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelegramFileAssets::route('/'),
        ];
    }
}
