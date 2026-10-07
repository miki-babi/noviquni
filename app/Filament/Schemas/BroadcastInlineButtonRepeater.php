<?php

namespace App\Filament\Schemas;

use App\Enums\BroadcastButtonType;
use App\Enums\TelegramButtonStyle;
use App\Models\TelegramCommand;
use App\Services\BroadcastService;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class BroadcastInlineButtonRepeater
{
    public static function make(string $name = 'buttons', bool $allowChoice = true): Repeater
    {
        $types = collect(BroadcastButtonType::cases())
            ->when(
                ! $allowChoice,
                fn ($collection) => $collection->reject(
                    fn (BroadcastButtonType $type) => $type === BroadcastButtonType::Choice
                )
            );

        return Repeater::make($name)
            ->schema([
                TextInput::make('label')
                    ->required()
                    ->maxLength(64),
                Select::make('type')
                    ->options($types->mapWithKeys(
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
                Select::make('action')
                    ->label('When tapped')
                    ->options([
                        'message' => 'Reply message',
                        'command' => 'Telegram command',
                    ])
                    ->default('message')
                    ->required()
                    ->live()
                    ->native(false)
                    ->visible(fn (Get $get): bool => $get('type') === BroadcastButtonType::Choice->value)
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        if ($state === 'message') {
                            $set('command', null);
                        }

                        if ($state === 'command') {
                            $set('response', null);
                        }
                    }),
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
                    ], true))
                    ->dehydrated(fn (Get $get): bool => in_array($get('type'), [
                        BroadcastButtonType::Command->value,
                        BroadcastButtonType::Text->value,
                    ], true)
                        || ($get('type') === BroadcastButtonType::Choice->value && $get('action') === 'command')),
                Select::make('telegram_command')
                    ->label('Telegram command')
                    ->options(fn (): array => TelegramCommand::query()
                        ->active()
                        ->orderBy('command')
                        ->pluck('command', 'command')
                        ->mapWithKeys(fn (string $command): array => [$command => '/'.$command])
                        ->all())
                    ->searchable()
                    ->native(false)
                    ->required(fn (Get $get): bool => $get('type') === BroadcastButtonType::Choice->value
                        && $get('action') === 'command')
                    ->visible(fn (Get $get): bool => $get('type') === BroadcastButtonType::Choice->value
                        && $get('action') === 'command')
                    ->helperText('Manage under Content → Telegram commands.')
                    ->live()
                    ->afterStateHydrated(function (Select $component, Get $get, Set $set): void {
                        $slug = $get('command') ?? $get('telegram_command');

                        if (($get('action') ?? null) === 'command' && filled($slug)) {
                            $normalized = TelegramCommand::normalizeCommand((string) $slug);
                            $component->state($normalized);
                            $set('command', $normalized);
                        }
                    })
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $set('command', filled($state) ? TelegramCommand::normalizeCommand($state) : null);
                    })
                    ->dehydrated(false),
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
                Textarea::make('response')
                    ->label('Reply message')
                    ->rows(3)
                    ->required(fn (Get $get): bool => $get('type') === BroadcastButtonType::Choice->value
                        && ($get('action') ?? 'message') === 'message')
                    ->visible(fn (Get $get): bool => $get('type') === BroadcastButtonType::Choice->value
                        && ($get('action') ?? 'message') === 'message')
                    ->helperText('Sent to the student when they tap this choice. Variables: {{first_name}}, {{university}}, {{stream}}, {{course}}, {{referral_count}}, {{referral_points}}')
                    ->dehydrated(fn (Get $get): bool => $get('type') === BroadcastButtonType::Choice->value
                        && ($get('action') ?? 'message') === 'message')
                    ->columnSpanFull(),
            ])
            ->defaultItems(0)
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
            ->addActionLabel('Add button')
            ->rules($allowChoice ? [
                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $choiceCount = collect(is_array($value) ? $value : [])
                        ->where('type', BroadcastButtonType::Choice->value)
                        ->count();

                    if ($choiceCount > BroadcastService::MaxChoiceButtons) {
                        $fail('You can add at most '.BroadcastService::MaxChoiceButtons.' choice buttons.');
                    }
                },
            ] : [])
            ->columnSpanFull();
    }
}
