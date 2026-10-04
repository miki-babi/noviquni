<?php

namespace App\Filament\Resources\Challenges\Tables;

use App\Enums\BroadcastButtonType;
use App\Enums\BroadcastStatus;
use App\Enums\ChallengeStatus;
use App\Models\Broadcast;
use App\Models\Challenge;
use App\Services\BroadcastService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ChallengesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->limit(40),
                TextColumn::make('cost_points')->label('Cost')->sortable(),
                TextColumn::make('reward_type')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('winners_count')->label('Winners'),
                TextColumn::make('max_winners')->label('Max')->placeholder('∞'),
                TextColumn::make('starts_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ends_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(ChallengeStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('broadcast')
                    ->label('Broadcast')
                    ->icon('heroicon-o-megaphone')
                    ->fillForm(fn (Challenge $record): array => [
                        'body' => trim(($record->title."\n\n".($record->description ?? ''))."\n\nCost: {$record->cost_points} referral points."),
                        'audience' => 'everyone',
                        'min_referrals' => null,
                        'max_referrals' => null,
                    ])
                    ->form([
                        Textarea::make('body')
                            ->required()
                            ->rows(6)
                            ->helperText('Variables: {{first_name}}, {{referral_count}}, {{referral_points}}'),
                        Select::make('audience')
                            ->options([
                                'everyone' => 'Everyone',
                                'premium' => 'Premium users',
                                'free' => 'Free users',
                            ])
                            ->default('everyone')
                            ->required(),
                        TextInput::make('min_referrals')
                            ->label('Min referrals')
                            ->numeric()
                            ->integer()
                            ->minValue(0),
                        TextInput::make('max_referrals')
                            ->label('Max referrals')
                            ->numeric()
                            ->integer()
                            ->minValue(0),
                    ])
                    ->action(function (Challenge $record, array $data, BroadcastService $broadcasts): void {
                        $targeting = [
                            'audience' => $data['audience'] ?? 'everyone',
                        ];

                        if (($data['min_referrals'] ?? null) !== null && $data['min_referrals'] !== '') {
                            $targeting['min_referrals'] = (int) $data['min_referrals'];
                        }

                        if (($data['max_referrals'] ?? null) !== null && $data['max_referrals'] !== '') {
                            $targeting['max_referrals'] = (int) $data['max_referrals'];
                        }

                        $broadcast = Broadcast::query()->create([
                            'title' => 'Challenge: '.$record->title,
                            'body' => $data['body'],
                            'targeting' => $targeting,
                            'buttons' => [[
                                'label' => 'View challenge',
                                'type' => BroadcastButtonType::Command->value,
                                'command' => 'challenge:'.$record->id,
                            ]],
                            'status' => BroadcastStatus::Draft,
                            'created_by' => auth()->id(),
                        ]);

                        $broadcasts->send($broadcast);

                        Notification::make()
                            ->title('Challenge broadcast sent')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
