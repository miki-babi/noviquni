<?php

namespace App\Filament\Resources\TelegramFileAssets\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TelegramFileAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('file_id')
                    ->label('Telegram file ID')
                    ->required()
                    ->maxLength(255),

                TextInput::make('file_unique_id')
                    ->label('Telegram unique file ID')
                    ->required()
                    ->maxLength(255)
                    ->unique('telegram_file_assets', 'file_unique_id', ignoreRecord: true)
                    ->disabled(fn (string $operation): bool => $operation === 'edit'),

                TextInput::make('file_name')
                    ->disabled(),

                TextInput::make('mime_type')
                    ->disabled(),

                TextInput::make('file_size')
                    ->numeric()
                    ->disabled(),

                TextInput::make('uploaded_by_username')
                    ->disabled(),
            ]);
    }
}
