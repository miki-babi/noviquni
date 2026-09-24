<?php

namespace App\Filament\Resources\TelegramFileAssets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelegramFileAssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('file_name')
                    ->label('Name')
                    ->searchable()
                    ->placeholder('Untitled'),
                TextColumn::make('file_id')
                    ->label('file_id')
                    ->copyable()
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('mime_type')
                    ->toggleable(),
                TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(function (?int $state): string {
                        if ($state === null) {
                            return '—';
                        }

                        if ($state < 1024) {
                            return $state.' B';
                        }

                        if ($state < 1024 * 1024) {
                            return round($state / 1024, 1).' KB';
                        }

                        return round($state / (1024 * 1024), 1).' MB';
                    })
                    ->toggleable(),
                TextColumn::make('uploaded_by_username')
                    ->label('Uploaded by')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
