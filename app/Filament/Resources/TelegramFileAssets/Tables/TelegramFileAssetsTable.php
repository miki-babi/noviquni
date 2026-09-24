<?php

namespace App\Filament\Resources\TelegramFileAssets\Tables;

use App\Filament\Resources\Learnings\Pages\CreateLearning;
use App\Models\TelegramFileAsset;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Redirect;

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
                Action::make('createResource')
                    ->label('Create resource')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->url(function (TelegramFileAsset $record): string {
                        $title = filled($record->file_name)
                            ? pathinfo($record->file_name, PATHINFO_FILENAME)
                            : null;

                        return CreateLearning::getCreateUrl([
                            'telegram_file_ids' => [$record->file_id],
                            'title' => filled($title) ? $title : null,
                        ]);
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('createResource')
                        ->label('Create resource')
                        ->icon(Heroicon::OutlinedDocumentPlus)
                        ->action(function (Collection $records) {
                            $fileIds = $records
                                ->pluck('file_id')
                                ->filter()
                                ->unique()
                                ->take(10)
                                ->values()
                                ->all();

                            return Redirect::to(CreateLearning::getCreateUrl([
                                'telegram_file_ids' => $fileIds,
                            ]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
