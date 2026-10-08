<?php

namespace App\Filament\Resources\Students\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DownloadsRelationManager extends RelationManager
{
    protected static string $relationship = 'downloads';

    protected static ?string $title = 'Recently studied';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['learningResource.course'])
                ->latest('id'))
            ->columns([
                TextColumn::make('learningResource.course.name')
                    ->label('Course')
                    ->placeholder('—'),
                TextColumn::make('learningResource.title')
                    ->label('Resource')
                    ->wrap(),
                TextColumn::make('learningResource.type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
                TextColumn::make('created_at')
                    ->label('Opened')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50]);
    }
}
