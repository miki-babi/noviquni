<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\PaymentService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('external_ref')->searchable(),
                TextColumn::make('provider')->badge()->toggleable(),
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('amount')->money('ETB'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('verified_at')->dateTime()->sortable(),
                TextColumn::make('fulfilled_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(PaymentStatus::class),
                SelectFilter::make('provider')->options([
                    Payment::PROVIDER_MANUAL => 'Manual',
                    Payment::PROVIDER_PAYMENT_BOT => 'Payment bot',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('verify')
                    ->visible(fn (Payment $record): bool => $record->isManuallyVerifiable())
                    ->requiresConfirmation()
                    ->action(fn (Payment $record) => app(PaymentService::class)->verify($record, auth()->user())),
                Action::make('reject')
                    ->color('danger')
                    ->visible(fn (Payment $record): bool => $record->isManuallyVerifiable())
                    ->requiresConfirmation()
                    ->action(fn (Payment $record) => app(PaymentService::class)->reject($record, auth()->user())),
                Action::make('revert')
                    ->color('warning')
                    ->visible(fn (Payment $record): bool => $record->isReversible())
                    ->requiresConfirmation()
                    ->modalHeading('Revert verified payment')
                    ->modalDescription('Marks the payment as rejected and removes premium granted by this payment (unless the student still has another premium source).')
                    ->action(fn (Payment $record) => app(PaymentService::class)->revert($record, auth()->user())),
            ]);
    }
}
