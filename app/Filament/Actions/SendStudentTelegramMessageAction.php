<?php

namespace App\Filament\Actions;

use App\Filament\Schemas\BroadcastInlineButtonRepeater;
use App\Models\User;
use App\Services\BroadcastService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class SendStudentTelegramMessageAction
{
    public static function make(): Action
    {
        return Action::make('sendTelegramMessage')
            ->label('Send Telegram message')
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn (User $record): bool => filled($record->telegram_id))
            ->modalHeading(fn (User $record): string => 'Send Telegram message to '.$record->name)
            ->form([
                Textarea::make('body')
                    ->label('Message')
                    ->required()
                    ->rows(6)
                    ->helperText('Variables: {{first_name}}, {{university}}, {{stream}}, {{course}}, {{referral_count}}, {{referral_points}}'),
                BroadcastInlineButtonRepeater::make()
                    ->label('Inline keyboard buttons'),
            ])
            ->action(function (User $record, array $data, BroadcastService $broadcasts): void {
                $sent = $broadcasts->sendToUser(
                    $record,
                    (string) $data['body'],
                    $data['buttons'] ?? null,
                );

                if (! $sent) {
                    Notification::make()
                        ->title('Message failed')
                        ->body('Could not send Telegram message. Check the bot token and that this student has a Telegram ID.')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Message sent to '.$record->name)
                    ->success()
                    ->send();
            });
    }
}
