<?php

namespace App\Filament\Resources\Students\Tables;

use App\Enums\SubscriptionSource;
use App\Services\PremiumService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('telegram_username')->toggleable(),
                TextColumn::make('stream.name')->toggleable(),
                TextColumn::make('university.name')->toggleable(),
                TextColumn::make('premium_until')->dateTime()->sortable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('stream')->relationship('stream', 'name'),
                SelectFilter::make('university')->relationship('university', 'name'),
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('grantPremium')
                    ->label('Grant premium')
                    ->requiresConfirmation()
                    ->action(fn ($record) => app(PremiumService::class)->grant($record, SubscriptionSource::Admin)),
                Action::make('disable')
                    ->visible(fn ($record) => $record->is_active)
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['is_active' => false])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}
