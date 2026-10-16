<?php

namespace App\Filament\Actions;

use App\Models\QuestionReport;
use App\Models\User;
use App\Services\QuestionReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ResolveQuestionReportAction
{
    public static function make(): Action
    {
        return Action::make('resolve')
            ->label('Resolve')
            ->color('success')
            ->visible(fn (QuestionReport $record): bool => $record->isPending())
            ->schema([
                Textarea::make('admin_notes')
                    ->label('Admin note')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (QuestionReport $record, array $data): void {
                /** @var User $admin */
                $admin = Auth::user();

                try {
                    app(QuestionReportService::class)->resolve(
                        $record,
                        $admin,
                        $data['admin_notes'] ?? null,
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title('Could not resolve')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Report resolved')
                    ->success()
                    ->send();
            });
    }
}
