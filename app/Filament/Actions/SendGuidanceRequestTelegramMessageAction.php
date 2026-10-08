<?php

namespace App\Filament\Actions;

use App\Enums\GuidanceRequestStatus;
use App\Filament\Schemas\BroadcastInlineButtonRepeater;
use App\Models\OpportunityGuidanceRequest;
use App\Services\BroadcastService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class SendGuidanceRequestTelegramMessageAction
{
    public static function make(): Action
    {
        return Action::make('sendTelegramMessage')
            ->label('Send Telegram message')
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn (OpportunityGuidanceRequest $record): bool => filled($record->user?->telegram_id))
            ->modalHeading(fn (OpportunityGuidanceRequest $record): string => 'Send Telegram message to '.($record->user?->name ?? 'student'))
            ->fillForm(fn (OpportunityGuidanceRequest $record): array => [
                'body' => static::defaultBody($record),
                'buttons' => [],
            ])
            ->schema([
                Textarea::make('body')
                    ->label('Message')
                    ->required()
                    ->rows(10)
                    ->helperText('Variables: {{first_name}}, {{university}}, {{stream}}, {{course}}, {{referral_count}}, {{referral_points}}'),
                BroadcastInlineButtonRepeater::make(allowChoice: false)
                    ->label('Inline keyboard buttons'),
            ])
            ->action(function (OpportunityGuidanceRequest $record, array $data, BroadcastService $broadcasts): void {
                $student = $record->user;

                if ($student === null) {
                    Notification::make()
                        ->title('Message failed')
                        ->body('This guidance request has no student.')
                        ->danger()
                        ->send();

                    return;
                }

                $sent = $broadcasts->sendToUser(
                    $student,
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
                    ->title('Message sent to '.$student->name)
                    ->success()
                    ->send();
            });
    }

    public static function defaultBody(OpportunityGuidanceRequest $record): string
    {
        $record->loadMissing(['opportunity', 'assignee']);

        $opportunity = $record->opportunity;
        $lines = [
            'Hi {{first_name}},',
            '',
            'Regarding your guidance request:',
            'Opportunity: '.($opportunity?->title ?? '—'),
        ];

        if (filled($opportunity?->partner_name)) {
            $lines[] = 'Partner: '.$opportunity->partner_name;
        }

        $lines[] = 'Status: '.match ($record->status) {
            GuidanceRequestStatus::Pending => 'pending',
            GuidanceRequestStatus::Assigned => 'assigned',
        };

        if ($record->assignee !== null) {
            $username = filled($record->assignee->telegram_username)
                ? ' (@'.ltrim((string) $record->assignee->telegram_username, '@').')'
                : '';
            $lines[] = 'Assigned guide: '.$record->assignee->name.$username;
        }

        $lines[] = '';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
