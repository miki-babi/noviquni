<?php

namespace App\Filament\Resources\ReferralRewards\Tables;

use App\Enums\RewardStatus;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReferralRewardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('amount')->money('ETB'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(RewardStatus::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->visible(fn ($record) => in_array($record->status, [RewardStatus::Pending, RewardStatus::Qualified], true))
                    ->action(fn ($record) => $record->update(['status' => RewardStatus::Approved])),
                Action::make('reject')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status !== RewardStatus::Paid)
                    ->action(fn ($record) => $record->update(['status' => RewardStatus::Rejected])),
                Action::make('markPaid')
                    ->label('Mark paid')
                    ->visible(fn ($record) => $record->status === RewardStatus::Approved)
                    ->action(fn ($record) => $record->update(['status' => RewardStatus::Paid])),
            ]);
    }
}
