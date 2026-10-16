<?php

namespace App\Filament\Resources\Broadcasts\Tables;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
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
                Action::make('queueSend')
                    ->label(fn (Broadcast $record): string => $record->status === BroadcastStatus::Sent
                        ? 'Queue again'
                        : 'Queue send')
                    ->visible(fn (Broadcast $record): bool => in_array($record->status, [
                        BroadcastStatus::Draft,
                        BroadcastStatus::Scheduled,
                        BroadcastStatus::Sent,
                    ], true))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Broadcast $record): string => $record->status === BroadcastStatus::Sent
                        ? 'Queue this broadcast again?'
                        : 'Queue this broadcast?')
                    ->modalDescription('Creates pending deliveries for each recipient. Cron sends about 50 users per minute.')
                    ->action(function (Broadcast $record, BroadcastService $broadcasts): void {
                        $queued = $broadcasts->queueSend($record);

                        $notification = Notification::make()
                            ->title($queued ? 'Broadcast queued' : 'Broadcast already sending');

                        if ($queued) {
                            $notification->success();
                        } else {
                            $notification->warning();
                        }

                        $notification->send();
                    }),
                Action::make('sendNow')
                    ->label(fn (Broadcast $record): string => $record->status === BroadcastStatus::Sent
                        ? 'Send again now'
                        : 'Send now')
                    ->color('warning')
                    ->visible(fn (Broadcast $record): bool => in_array($record->status, [
                        BroadcastStatus::Draft,
                        BroadcastStatus::Scheduled,
                        BroadcastStatus::Sent,
                    ], true))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Broadcast $record): string => $record->status === BroadcastStatus::Sent
                        ? 'Send this broadcast again now?'
                        : 'Send this broadcast now?')
                    ->modalDescription('Delivers to the full audience immediately in this request. Large audiences may take a while.')
                    ->action(function (Broadcast $record, BroadcastService $broadcasts): void {
                        $broadcasts->send($record);

                        Notification::make()
                            ->title('Broadcast sent')
                            ->success()
                            ->send();
                    }),
                Action::make('deleteFromTelegram')
                    ->label('Delete from Telegram')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn (Broadcast $record): bool => $record->status === BroadcastStatus::Sent
                        && $record->deliveries()
                            ->where('status', 'sent')
                            ->whereNotNull('telegram_message_id')
                            ->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Delete this broadcast from Telegram?')
                    ->modalDescription('Removes this message from users’ Telegram chats. Only works for messages sent after message IDs were stored, and within Telegram’s ~48h delete window.')
                    ->action(function (Broadcast $record, BroadcastService $broadcasts): void {
                        $counts = $broadcasts->deleteFromTelegram($record);

                        Notification::make()
                            ->title('Telegram delete finished')
                            ->body("Deleted: {$counts['deleted']}. Failed: {$counts['failed']}. Skipped: {$counts['skipped']}.")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
