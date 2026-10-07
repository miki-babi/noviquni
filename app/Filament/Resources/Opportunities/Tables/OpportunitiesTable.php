<?php

namespace App\Filament\Resources\Opportunities\Tables;

use App\Enums\OpportunityType;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class OpportunitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->limit(40),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (OpportunityType $state): string => $state->label()),
                IconColumn::make('is_published')->boolean()->label('Published'),
                TextColumn::make('deadline')->date()->placeholder('—')->sortable(),
                TextColumn::make('sort_order')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->since(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('type')->options(collect(OpportunityType::cases())->mapWithKeys(
                    fn (OpportunityType $type) => [$type->value => $type->label()]
                )->all()),
                TernaryFilter::make('is_published')->label('Published'),
            ])
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
