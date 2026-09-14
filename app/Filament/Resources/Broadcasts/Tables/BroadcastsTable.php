<?php

namespace App\Filament\Resources\Broadcasts\Tables;

use App\Enums\BroadcastStatus;
use App\Services\BroadcastService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
                    ->label(fn ($record): string => $record->status === BroadcastStatus::Sent ? 'Send again' : 'Send now')
                    ->visible(fn ($record) => in_array($record->status, [
                        BroadcastStatus::Draft,
                        BroadcastStatus::Scheduled,
                        BroadcastStatus::Sent,
                    ], true))
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record): string => $record->status === BroadcastStatus::Sent
                        ? 'Send this broadcast again?'
                        : 'Send this broadcast now?')
                    ->modalDescription(fn ($record): ?string => $record->status === BroadcastStatus::Sent
                        ? 'This will deliver the message to the audience again.'
                        : null)
                    ->action(function ($record, BroadcastService $broadcasts): void {
                        $broadcasts->send($record);

                        Notification::make()
                            ->title('Broadcast sent')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
