<?php

namespace App\Filament\Resources\TelegramCommands;

use App\Filament\Resources\TelegramCommands\Pages\CreateTelegramCommand;
use App\Filament\Resources\TelegramCommands\Pages\EditTelegramCommand;
use App\Filament\Resources\TelegramCommands\Pages\ListTelegramCommands;
use App\Filament\Resources\TelegramCommands\Schemas\TelegramCommandForm;
use App\Filament\Resources\TelegramCommands\Tables\TelegramCommandsTable;
use App\Models\TelegramCommand;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TelegramCommandResource extends Resource
{
    protected static ?string $model = TelegramCommand::class;

    protected static ?string $modelLabel = 'Telegram command';

    protected static ?string $pluralModelLabel = 'Telegram commands';

    protected static ?string $navigationLabel = 'Telegram commands';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'command';

    public static function form(Schema $schema): Schema
    {
        return TelegramCommandForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelegramCommandsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('fileAssets');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelegramCommands::route('/'),
            'create' => CreateTelegramCommand::route('/create'),
            'edit' => EditTelegramCommand::route('/{record}/edit'),
        ];
    }
}
