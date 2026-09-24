<?php

namespace App\Filament\Resources\TelegramCommands\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TelegramCommandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('command')
                    ->label('Command')
                    ->formatStateUsing(fn (string $state): string => '/'.$state)
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('response')
                    ->label('Sends')
                    ->state(function ($record): string {
                        $parts = [];

                        if (filled($record->message)) {
                            $parts[] = 'message';
                        }

                        $fileCount = $record->file_assets_count ?? $record->fileAssets()->count();

                        if ($fileCount > 0) {
                            $parts[] = $fileCount === 1 ? '1 file' : $fileCount.' files';
                        }

                        if ($record->learning_resource_id !== null) {
                            $parts[] = 'resource';
                        }

                        return $parts === [] ? '—' : implode(' · ', $parts);
                    }),
                TextColumn::make('learningResource.title')
                    ->label('Resource')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('command')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
