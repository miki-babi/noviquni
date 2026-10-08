<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentStatus;
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
                TextColumn::make('deposit_id')->label('Deposit')->searchable()->toggleable()->placeholder('—'),
                TextColumn::make('deposit_status')->label('Deposit status')->badge()->toggleable()->placeholder('—'),
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
                    'manual' => 'Manual',
                    'verify_checkout' => 'Verify Checkout',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('verify')
                    ->visible(fn ($record) => $record->provider === 'manual' && $record->status === PaymentStatus::Pending)
                    ->requiresConfirmation()
                    ->action(fn ($record) => app(PaymentService::class)->verify($record, auth()->user())),
                Action::make('reject')
                    ->color('danger')
                    ->visible(fn ($record) => $record->provider === 'manual' && $record->status === PaymentStatus::Pending)
                    ->requiresConfirmation()
                    ->action(fn ($record) => app(PaymentService::class)->reject($record, auth()->user())),
            ]);
    }
}
