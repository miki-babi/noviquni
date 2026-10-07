<?php

namespace App\Filament\Resources\OpportunityGuidanceRequests\Tables;

use App\Enums\GuidanceRequestStatus;
use App\Models\OpportunityGuidanceRequest;
use App\Models\User;
use App\Services\OpportunityGuidanceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use InvalidArgumentException;

class OpportunityGuidanceRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable()
                    ->description(fn (OpportunityGuidanceRequest $record): ?string => filled($record->user?->telegram_username)
                        ? '@'.$record->user->telegram_username
                        : null),
                TextColumn::make('opportunity.title')
                    ->label('Opportunity')
                    ->searchable()
                    ->sortable()
                    ->description(fn (OpportunityGuidanceRequest $record): ?string => filled($record->opportunity?->partner_name)
                        ? (string) $record->opportunity->partner_name
                        : null),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('assignee.name')
                    ->label('Assignee')
                    ->placeholder('—')
                    ->description(fn (OpportunityGuidanceRequest $record): ?string => filled($record->assignee?->telegram_username)
                        ? '@'.$record->assignee->telegram_username
                        : null),
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('assigned_at')
                    ->label('Assigned')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(GuidanceRequestStatus::class),
            ])
            ->recordActions([
                Action::make('assign')
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
                    }),
            ]);
    }
}
