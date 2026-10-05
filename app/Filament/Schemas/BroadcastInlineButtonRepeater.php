<?php

namespace App\Filament\Schemas;

use App\Enums\BroadcastButtonType;
use App\Enums\TelegramButtonStyle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

class BroadcastInlineButtonRepeater
{
    public static function make(string $name = 'buttons'): Repeater
    {
        return Repeater::make($name)
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
            ->columnSpanFull();
    }
}
