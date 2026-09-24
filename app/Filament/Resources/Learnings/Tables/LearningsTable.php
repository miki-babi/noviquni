<?php

namespace App\Filament\Resources\Learnings\Tables;

use App\Models\LearningResource;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class LearningsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->limit(40),
                TextColumn::make('type')->badge(),
                TextColumn::make('delivery')
                    ->label('Delivery')
                    ->badge()
                    ->state(fn (LearningResource $record): string => $record->deliveryMethodLabel())
                    ->color(fn (LearningResource $record): string => match ($record->deliveryMethod()) {
                        'telegram' => 'success',
                        'disk' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (LearningResource $record): ?string => match ($record->deliveryMethod()) {
                        'telegram' => $record->telegramFileCount().' file_id(s)',
                        'disk' => $record->diskFileCount().' disk file(s)',
                        default => null,
                    }),
                TextColumn::make('stream.name'),
                TextColumn::make('course.name'),
                IconColumn::make('is_premium')->boolean(),
                IconColumn::make('is_published')->boolean(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->filters([
                SelectFilter::make('stream')->relationship('stream', 'name'),
                SelectFilter::make('course')->relationship('course', 'name'),
                SelectFilter::make('delivery')
                    ->label('Delivery')
                    ->options([
                        'telegram' => 'Telegram file_id',
                        'disk' => 'Disk upload',
                        'mini_app' => 'Mini App',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'telegram' => $query->whereNotNull('telegram_files')
                                ->where('telegram_files', '!=', '[]')
                                ->where('telegram_files', '!=', 'null'),
                            'disk' => $query
                                ->where(function (Builder $builder): void {
                                    $builder->whereNull('telegram_files')
                                        ->orWhere('telegram_files', '[]')
                                        ->orWhere('telegram_files', 'null');
                                })
                                ->whereNotNull('files')
                                ->where('files', '!=', '[]')
                                ->where('files', '!=', 'null'),
                            'mini_app' => $query
                                ->where(function (Builder $builder): void {
                                    $builder->whereNull('telegram_files')
                                        ->orWhere('telegram_files', '[]')
                                        ->orWhere('telegram_files', 'null');
                                })
                                ->where(function (Builder $builder): void {
                                    $builder->whereNull('files')
                                        ->orWhere('files', '[]')
                                        ->orWhere('files', 'null');
                                }),
                            default => $query,
                        };
                    }),
                TernaryFilter::make('is_premium'),
                TernaryFilter::make('is_published'),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->action(fn (Collection $records) => $records->each->update(['is_published' => true])),
                    BulkAction::make('unpublish')
                        ->action(fn (Collection $records) => $records->each->update(['is_published' => false])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
