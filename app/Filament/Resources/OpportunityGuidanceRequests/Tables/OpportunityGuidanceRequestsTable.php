<?php

namespace App\Filament\Resources\OpportunityGuidanceRequests\Tables;

use App\Enums\GuidanceRequestStatus;
use App\Filament\Actions\AssignGuidanceRequestAction;
use App\Filament\Actions\SendGuidanceRequestTelegramMessageAction;
use App\Models\OpportunityGuidanceRequest;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                ViewAction::make(),
                AssignGuidanceRequestAction::make(),
                SendGuidanceRequestTelegramMessageAction::make(),
            ]);
    }
}
