<?php

namespace App\Filament\Resources\Challenges\Schemas;

use App\Enums\ChallengeRewardType;
use App\Enums\ChallengeStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ChallengeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('cost_points')
                    ->label('Cost (referral points)')
                    ->numeric()
                    ->integer()
                    ->required()
                    ->minValue(1),
                Select::make('reward_type')
                    ->options(collect(ChallengeRewardType::cases())->mapWithKeys(
                        fn (ChallengeRewardType $type) => [$type->value => $type->label()]
                    )->all())
                    ->required()
                    ->live()
                    ->native(false),
                TextInput::make('reward_value')
                    ->label('Premium days')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required(fn (Get $get): bool => $get('reward_type') === ChallengeRewardType::PremiumDays->value)
                    ->visible(fn (Get $get): bool => $get('reward_type') === ChallengeRewardType::PremiumDays->value),
                Textarea::make('reward_message')
                    ->label('Prize message')
                    ->rows(3)
                    ->helperText('Sent to the user when the challenge auto-completes.')
                    ->required(fn (Get $get): bool => $get('reward_type') === ChallengeRewardType::Message->value)
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(collect(ChallengeStatus::cases())->mapWithKeys(
                        fn (ChallengeStatus $status) => [$status->value => $status->label()]
                    )->all())
                    ->required()
                    ->native(false)
                    ->default(ChallengeStatus::Draft->value),
                DateTimePicker::make('starts_at')
                    ->helperText('UTC. Leave empty to make the challenge available immediately.'),
                DateTimePicker::make('ends_at')
                    ->helperText('UTC. Leave empty for no end date.'),
                TextInput::make('max_winners')
                    ->label('Max winners')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->helperText('Leave empty for unlimited winners.'),
            ]);
    }
}
