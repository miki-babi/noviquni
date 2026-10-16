<?php

namespace App\Filament\Resources\QuestionReports\Schemas;

use App\Enums\QuestionReportReason;
use App\Enums\QuestionReportSource;
use App\Enums\QuestionReportStatus;
use App\Filament\Resources\Learnings\LearningResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\QuestionReport;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QuestionReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Report')
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (QuestionReportStatus $state): string => $state->label()),
                        TextEntry::make('reason')
                            ->badge()
                            ->formatStateUsing(fn (QuestionReportReason $state): string => $state->label()),
                        TextEntry::make('source')
                            ->badge()
                            ->formatStateUsing(fn (QuestionReportSource $state): string => $state->label()),
                        TextEntry::make('question_index')
                            ->label('Question #')
                            ->formatStateUsing(fn (int $state): string => (string) ($state + 1)),
                        TextEntry::make('question_label')
                            ->label('Question')
                            ->columnSpanFull(),
                        TextEntry::make('note')
                            ->label('Student note')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label('Reported at')
                            ->dateTime(),
                    ])
                    ->columns(3),
                Section::make('Student')
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Name')
                            ->url(fn (QuestionReport $record): ?string => $record->user_id
                                ? StudentResource::getUrl('edit', ['record' => $record->user_id])
                                : null),
                        TextEntry::make('user.telegram_username')
                            ->label('Telegram')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? '@'.ltrim($state, '@') : '—')
                            ->copyable(fn (?string $state): bool => filled($state))
                            ->copyableState(fn (?string $state): ?string => filled($state) ? ltrim($state, '@') : null),
                    ])
                    ->columns(2),
                Section::make('Source')
                    ->schema([
                        TextEntry::make('learningResource.title')
                            ->label('Learning resource')
                            ->placeholder('—')
                            ->url(fn (QuestionReport $record): ?string => $record->learning_resource_id
                                ? LearningResource::getUrl('edit', ['record' => $record->learning_resource_id])
                                : null),
                        TextEntry::make('dailyChallenge.challenge_date')
                            ->label('Daily challenge')
                            ->date()
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make('Resolution')
                    ->schema([
                        TextEntry::make('resolver.name')
                            ->label('Resolved by')
                            ->placeholder('—'),
                        TextEntry::make('resolved_at')
                            ->label('Resolved at')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('admin_notes')
                            ->label('Admin notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
