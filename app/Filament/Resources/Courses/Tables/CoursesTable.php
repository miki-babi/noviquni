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
use Filament\Tables\Table;

class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                Action::make('createResource')
                    ->label('Create resource')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->url(fn (Course $record): string => CreateLearning::getCreateUrl([
                        'course_id' => $record->id,
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
