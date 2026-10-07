<?php

namespace App\Filament\Resources\OpportunityGuidanceRequests\Schemas;

use App\Filament\Resources\Students\StudentResource;
use App\Models\OpportunityGuidanceRequest;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OpportunityGuidanceRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Student')
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Name')
                            ->url(fn (OpportunityGuidanceRequest $record): ?string => $record->user_id
                                ? StudentResource::getUrl('edit', ['record' => $record->user_id])
                                : null),
                        TextEntry::make('user.telegram_username')
                            ->label('Telegram')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? '@'.ltrim($state, '@') : '—')
                            ->copyable(fn (?string $state): bool => filled($state))
                            ->copyableState(fn (?string $state): ?string => filled($state) ? ltrim($state, '@') : null),
                        TextEntry::make('user.telegram_id')
                            ->label('Telegram ID')
                            ->placeholder('—'),
                    ])
                    ->columns(3),
                Section::make('Opportunity')
                    ->schema([
                        TextEntry::make('opportunity.title')
                            ->label('Title'),
                        TextEntry::make('opportunity.partner_name')
                            ->label('Partner')
                            ->placeholder('—'),
                        TextEntry::make('opportunity.type')
                            ->label('Type')
                            ->badge(),
                    ])
                    ->columns(3),
                Section::make('Assignment')
                    ->schema([
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('assignee.name')
                            ->label('Assignee')
                            ->placeholder('—')
                            ->formatStateUsing(function (?string $state, OpportunityGuidanceRequest $record): string {
                                if ($state === null) {
                                    return '—';
                                }

                                $username = $record->assignee?->telegram_username;

                                return filled($username)
                                    ? $state.' (@'.ltrim((string) $username, '@').')'
                                    : $state;
                            }),
                        TextEntry::make('created_at')
                            ->label('Requested at')
                            ->dateTime(),
                        TextEntry::make('assigned_at')
                            ->label('Assigned at')
                            ->dateTime()
                            ->placeholder('—'),
                    ])
                    ->columns(2),
            ]);
    }
}
