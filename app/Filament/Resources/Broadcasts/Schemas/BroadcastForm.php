<?php

namespace App\Filament\Resources\Broadcasts\Schemas;

use App\Filament\Schemas\BroadcastInlineButtonRepeater;
use App\Models\Course;
use App\Models\Stream;
use App\Models\University;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BroadcastForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title'),
                Textarea::make('body')
                    ->required()
                    ->rows(6)
                    ->helperText('Variables: {{first_name}}, {{university}}, {{stream}}, {{course}}, {{referral_count}}, {{referral_points}}')
                    ->columnSpanFull(),
                Section::make('Audience')
                    ->schema([
                        Select::make('targeting.audience')
                            ->options([
                                'everyone' => 'Everyone',
                                'premium' => 'Premium users',
                                'free' => 'Free users',
                            ])
                            ->default('everyone')
                            ->required(),
                        Select::make('targeting.university_id')
                            ->label('University')
                            ->options(fn () => University::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable(),
                        Select::make('targeting.stream_id')
                            ->label('Stream')
                            ->options(fn () => Stream::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable(),
                        Select::make('targeting.course_id')
                            ->label('Course')
                            ->options(fn () => Course::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable(),
                        TextInput::make('targeting.min_referrals')
                            ->label('Min referrals')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->helperText('Qualified referrals — leave empty for no minimum.'),
                        TextInput::make('targeting.max_referrals')
                            ->label('Max referrals')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->helperText('Qualified referrals — leave empty for no maximum.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Inline keyboard buttons')
                    ->description('Optional Telegram buttons under the message. Each row is one button (stacked).')
                    ->schema([
                        BroadcastInlineButtonRepeater::make(),
                    ])
                    ->columnSpanFull(),
                DateTimePicker::make('scheduled_at'),
            ]);
    }
}
