<?php

namespace App\Filament\Resources\Withdrawals\Tables;

use App\Enums\WithdrawalStatus;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WithdrawalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('amount')->money('ETB'),
                TextColumn::make('method'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(WithdrawalStatus::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->visible(fn ($record) => $record->status === WithdrawalStatus::Pending)
                    ->action(fn ($record) => $record->update(['status' => WithdrawalStatus::Approved])),
                Action::make('reject')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === WithdrawalStatus::Pending)
                    ->action(fn ($record) => $record->update(['status' => WithdrawalStatus::Rejected])),
                Action::make('markPaid')
                    ->label('Mark paid')
                    ->visible(fn ($record) => $record->status === WithdrawalStatus::Approved)
                    ->action(fn ($record) => $record->update(['status' => WithdrawalStatus::Paid])),
            ]);
    }
}
