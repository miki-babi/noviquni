<?php

namespace App\Filament\Resources\Broadcasts\Schemas;

use App\Enums\BroadcastButtonType;
use App\Enums\TelegramButtonStyle;
use App\Models\Course;
use App\Models\Stream;
use App\Models\University;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                        Repeater::make('buttons')
                            ->schema([
                                TextInput::make('label')
                                    ->required()
                                    ->maxLength(64),
                                Select::make('type')
                                    ->options(collect(BroadcastButtonType::cases())->mapWithKeys(
                                        fn (BroadcastButtonType $type) => [$type->value => $type->label()]
                                    )->all())
                                    ->required()
                                    ->live()
                                    ->native(false),
                                Select::make('style')
                                    ->label('Color')
                                    ->options(
                                        collect(TelegramButtonStyle::cases())
                                            ->mapWithKeys(fn (TelegramButtonStyle $style) => [$style->value => $style->label()])
                                            ->prepend('Default', '')
                                            ->all()
                                    )
                                    ->placeholder('Default')
                                    ->native(false),
                                TextInput::make('command')
                                    ->label(fn (Get $get): string => $get('type') === BroadcastButtonType::Text->value
                                        ? 'Reply text'
                                        : 'Command / callback')
                                    ->helperText(fn (Get $get): string => $get('type') === BroadcastButtonType::Text->value
                                        ? 'Sent to the bot as if the student typed this. Leave empty to use the button label.'
                                        : 'Sent as callback_data. Examples: premium_pay, ⭐ Premium, /start')
                                    ->maxLength(64)
                                    ->required(fn (Get $get): bool => $get('type') === BroadcastButtonType::Command->value)
                                    ->visible(fn (Get $get): bool => in_array($get('type'), [
                                        BroadcastButtonType::Command->value,
                                        BroadcastButtonType::Text->value,
                                    ], true)),
                                TextInput::make('url')
                                    ->label('URL')
                                    ->url()
                                    ->maxLength(2048)
                                    ->required(fn (Get $get): bool => in_array($get('type'), [
                                        BroadcastButtonType::Url->value,
                                        BroadcastButtonType::MiniApp->value,
                                    ], true))
                                    ->visible(fn (Get $get): bool => in_array($get('type'), [
                                        BroadcastButtonType::Url->value,
                                        BroadcastButtonType::MiniApp->value,
                                    ], true))
                                    ->helperText(fn (Get $get): string => $get('type') === BroadcastButtonType::MiniApp->value
                                        ? 'HTTPS URL of your Telegram Mini App'
                                        : 'Opens this URL in the browser / Telegram'),
                            ])
                            ->defaultItems(0)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->addActionLabel('Add button')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                DateTimePicker::make('scheduled_at'),
            ]);
    }
}
