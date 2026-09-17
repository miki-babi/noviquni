<?php

namespace App\Filament\Resources\StudyPlans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class StudyPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->limit(40),
                TextColumn::make('type')->badge(),
                TextColumn::make('course.name')->sortable(),
                TextColumn::make('week_number')->label('Week'),
                IconColumn::make('is_premium')->boolean(),
                IconColumn::make('is_published')->boolean(),
            ])
            ->filters([
                SelectFilter::make('course')->relationship('course', 'name'),
                SelectFilter::make('type'),
                TernaryFilter::make('is_premium'),
                TernaryFilter::make('is_published'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
