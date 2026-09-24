<?php

namespace App\Filament\Resources\Courses\Tables;

use App\Filament\Resources\Learnings\Pages\CreateLearning;
use App\Models\Course;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('stream.name')->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('stream')->relationship('stream', 'name'),
            ])
            ->recordActions([
                Action::make('createResource')
                    ->label('Create resource')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->url(fn (Course $record): string => CreateLearning::getCreateUrl([
                        'course_id' => $record->id,
                        'stream_id' => $record->stream_id,
                    ])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
