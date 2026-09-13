<?php

namespace App\Filament\Resources\Broadcasts\Tables;

use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastJob;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BroadcastsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->limit(30),
                TextColumn::make('status')->badge(),
                TextColumn::make('buttons')
                    ->label('Buttons')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? (string) count($state) : '0'),
                TextColumn::make('scheduled_at')->dateTime(),
                TextColumn::make('sent_at')->dateTime(),
                TextColumn::make('deliveries_count')->counts('deliveries')->label('Deliveries'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(BroadcastStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('sendNow')
                    ->label('Send now')
                    ->visible(fn ($record) => in_array($record->status, [BroadcastStatus::Draft, BroadcastStatus::Scheduled], true))
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->update(['status' => BroadcastStatus::Sending, 'scheduled_at' => null]);
                        SendBroadcastJob::dispatch($record);
                    }),
            ]);
    }
}
