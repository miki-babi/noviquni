<?php

namespace App\Filament\Resources\PremiumUsers\Tables;

use App\Enums\SubscriptionSource;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PremiumService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PremiumUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('telegram_username')
                    ->label('Telegram')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? '@'.$state : '—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('premium_source')
                    ->label('Source')
                    ->badge()
                    ->getStateUsing(function (User $record): string {
                        $subscription = $record->subscriptions->first();

                        if ($subscription?->source instanceof SubscriptionSource) {
                            return Str::headline($subscription->source->value);
                        }

                        return $record->payments->first() !== null ? 'Payment' : 'Unknown';
                    }),
                TextColumn::make('latest_payment')
                    ->label('Payment ref')
                    ->getStateUsing(function (User $record): string {
                        return $record->payments->first()?->external_ref ?? '—';
                    })
                    ->description(function (User $record): ?string {
                        $payment = $record->payments->first();

                        if ($payment === null) {
                            return null;
                        }

                        return $payment->amount.' '.$payment->currency.' · '.$payment->provider;
                    }),
                TextColumn::make('premium_since')
                    ->label('Since')
                    ->getStateUsing(function (User $record): ?string {
                        $subscription = $record->subscriptions->first();
                        $payment = $record->payments->first();

                        $at = $subscription?->starts_at ?? $payment?->verified_at ?? $payment?->fulfilled_at;

                        return $at?->toDateTimeString();
                    }),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('premium_source')
                    ->label('Source')
                    ->options(
                        collect(SubscriptionSource::cases())
                            ->mapWithKeys(fn (SubscriptionSource $source): array => [
                                $source->value => Str::headline($source->value),
                            ])
                            ->all(),
                    )
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'subscriptions',
                            fn ($subscriptions) => $subscriptions->where('source', $value),
                        );
                    }),
            ])
            ->recordActions([
                Action::make('revertPayment')
                    ->label('Revert payment')
                    ->color('warning')
                    ->visible(fn (User $record): bool => $record->payments->first() !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Revert verified payment')
                    ->modalDescription(fn (User $record): string => 'Rejects payment '
                        .($record->payments->first()?->external_ref ?? '')
                        .' and removes premium from that payment unless another premium source remains.')
                    ->action(function (User $record): void {
                        /** @var Payment|null $payment */
                        $payment = $record->payments->first();

                        if ($payment === null) {
                            return;
                        }

                        app(PaymentService::class)->revert($payment, auth()->user());
                    }),
                Action::make('revokePremium')
                    ->label('Revoke premium')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke premium')
                    ->modalDescription('Turns off premium for this student without changing payment history. Prefer “Revert payment” when premium came from a payment.')
                    ->action(fn (User $record) => app(PremiumService::class)->revoke($record)),
            ]);
    }
}
