<?php

namespace App\Filament\Resources\Referrals\Tables;

use App\Enums\ReferralStatus;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Referral;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReferralsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('referrer.name')
                    ->label('Referrer')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Referral $record): ?string => filled($record->referrer?->telegram_username)
                        ? '@'.$record->referrer->telegram_username
                        : null),
                TextColumn::make('referred.name')
                    ->label('Referred')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Referral $record): ?string => filled($record->referred?->telegram_username)
                        ? '@'.$record->referred->telegram_username
                        : null),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(ReferralStatus::class),
            ])
            ->recordActions([
                Action::make('viewReferrer')
                    ->label('Referrer')
                    ->icon('heroicon-o-user')
                    ->url(fn (Referral $record): ?string => $record->referrer_id
                        ? StudentResource::getUrl('edit', ['record' => $record->referrer_id])
                        : null)
                    ->visible(fn (Referral $record): bool => $record->referrer_id !== null),
                Action::make('viewReferred')
                    ->label('Referred')
                    ->icon('heroicon-o-user')
                    ->url(fn (Referral $record): ?string => $record->referred_id
                        ? StudentResource::getUrl('edit', ['record' => $record->referred_id])
                        : null)
                    ->visible(fn (Referral $record): bool => $record->referred_id !== null),
            ]);
    }
}
