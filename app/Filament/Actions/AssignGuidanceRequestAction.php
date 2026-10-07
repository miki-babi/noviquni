<?php

namespace App\Filament\Actions;

use App\Enums\GuidanceRequestStatus;
use App\Models\OpportunityGuidanceRequest;
use App\Models\User;
use App\Services\OpportunityGuidanceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use InvalidArgumentException;

class AssignGuidanceRequestAction
{
    public static function make(): Action
    {
        return Action::make('assign')
            ->label(fn (OpportunityGuidanceRequest $record): string => $record->status === GuidanceRequestStatus::Assigned
                ? 'Reassign'
                : 'Assign')
            ->schema([
                Select::make('assigned_to_user_id')
                    ->label('Guide')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => User::query()
                        ->whereNotNull('telegram_username')
                        ->where('telegram_username', '!=', '')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (User $user): array => [
                            $user->id => $user->name.' (@'.ltrim((string) $user->telegram_username, '@').')',
                        ])
                        ->all())
                    ->default(fn (OpportunityGuidanceRequest $record): ?int => $record->assigned_to_user_id),
            ])
            ->action(function (OpportunityGuidanceRequest $record, array $data): void {
                $assignee = User::query()->findOrFail($data['assigned_to_user_id']);

                try {
                    app(OpportunityGuidanceService::class)->assign($record, $assignee);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title('Assignment failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Guide assigned')
                    ->success()
                    ->send();
            });
    }
}
