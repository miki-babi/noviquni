<?php

namespace App\Filament\Resources\QuestionReports\Tables;

use App\Enums\QuestionReportReason;
use App\Enums\QuestionReportSource;
use App\Enums\QuestionReportStatus;
use App\Filament\Actions\DismissQuestionReportAction;
use App\Filament\Actions\EditQuestionReportAction;
use App\Filament\Actions\ResolveQuestionReportAction;
use App\Models\QuestionReport;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuestionReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (QuestionReportStatus $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (QuestionReportReason $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (QuestionReportSource $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable()
                    ->description(fn (QuestionReport $record): ?string => filled($record->user?->telegram_username)
                        ? '@'.$record->user->telegram_username
                        : null),
                TextColumn::make('target')
                    ->label('Target')
                    ->state(function (QuestionReport $record): string {
                        if ($record->learningResource !== null) {
                            return (string) $record->learningResource->title;
                        }

                        if ($record->dailyChallenge !== null) {
                            return 'Daily '.$record->dailyChallenge->challenge_date->toDateString();
                        }

                        return '—';
                    })
                    ->wrap(),
                TextColumn::make('question_label')
                    ->label('Question')
                    ->limit(60)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Reported')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(QuestionReportStatus::cases())
                        ->mapWithKeys(fn (QuestionReportStatus $status): array => [
                            $status->value => $status->label(),
                        ])
                        ->all())
                    ->default(QuestionReportStatus::Pending->value),
                SelectFilter::make('reason')
                    ->options(collect(QuestionReportReason::cases())
                        ->mapWithKeys(fn (QuestionReportReason $reason): array => [
                            $reason->value => $reason->label(),
                        ])
                        ->all()),
                SelectFilter::make('source')
                    ->options(collect(QuestionReportSource::cases())
                        ->mapWithKeys(fn (QuestionReportSource $source): array => [
                            $source->value => $source->label(),
                        ])
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditQuestionReportAction::make(),
                ResolveQuestionReportAction::make(),
                DismissQuestionReportAction::make(),
            ]);
    }
}
