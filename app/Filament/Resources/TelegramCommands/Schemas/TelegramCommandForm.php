<?php

namespace App\Filament\Resources\TelegramCommands\Schemas;

use App\Models\LearningResource;
use App\Models\TelegramCommand;
use App\Models\TelegramFileAsset;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TelegramCommandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('command')
                    ->label('Command')
                    ->prefix('/')
                    ->required()
                    ->maxLength(32)
                    ->helperText('Letters, numbers, and underscores only. /start is reserved.')
                    ->dehydrateStateUsing(fn (?string $state): string => TelegramCommand::normalizeCommand((string) $state))
                    ->rules([
                        fn (?TelegramCommand $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            $command = TelegramCommand::normalizeCommand((string) $value);

                            if ($command === '' || ! preg_match('/^[a-z0-9_]{1,32}$/', $command)) {
                                $fail('Use letters, numbers, and underscores only (max 32).');

                                return;
                            }

                            if (TelegramCommand::isReservedCommand($command)) {
                                $fail('The /start command is reserved.');

                                return;
                            }

                            $exists = TelegramCommand::query()
                                ->where('command', $command)
                                ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                                ->exists();

                            if ($exists) {
                                $fail('This command is already taken.');
                            }
                        },
                    ]),
                Textarea::make('message')
                    ->label('Message')
                    ->rows(5)
                    ->helperText('Optional Telegram HTML. Sent before any files or resource.')
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $hasMessage = filled($get('message'));
                            $hasFiles = collect($get('vault_files') ?? [])
                                ->contains(fn (mixed $row): bool => filled(is_array($row) ? ($row['telegram_file_asset_id'] ?? null) : null));
                            $hasResource = filled($get('learning_resource_id'));

                            if (! $hasMessage && ! $hasFiles && ! $hasResource) {
                                $fail('Add a message, at least one vault file, or a learning resource.');
                            }
                        },
                    ])
                    ->columnSpanFull(),
                Repeater::make('vault_files')
                    ->label('Vault files')
                    ->schema([
                        Select::make('telegram_file_asset_id')
                            ->label('File')
                            ->options(fn (): array => TelegramFileAsset::query()
                                ->orderByDesc('id')
                                ->limit(200)
                                ->get()
                                ->mapWithKeys(fn (TelegramFileAsset $asset): array => [
                                    $asset->id => $asset->label(),
                                ])
                                ->all())
                            ->getSearchResultsUsing(function (string $search): array {
                                $query = TelegramFileAsset::query()->orderByDesc('id');

                                if (filled($search)) {
                                    $like = '%'.$search.'%';
                                    $query->where(function ($builder) use ($like): void {
                                        $builder->where('file_name', 'like', $like)
                                            ->orWhere('file_id', 'like', $like)
                                            ->orWhere('uploaded_by_username', 'like', $like);
                                    });
                                }

                                return $query
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (TelegramFileAsset $asset): array => [
                                        $asset->id => $asset->label(),
                                    ])
                                    ->all();
                            })
                            ->getOptionLabelUsing(fn ($value): ?string => TelegramFileAsset::query()->find($value)?->label())
                            ->searchable()
                            ->required(),
                    ])
                    ->reorderable()
                    ->defaultItems(0)
                    ->collapsible()
                    ->itemLabel(function (array $state): ?string {
                        $id = $state['telegram_file_asset_id'] ?? null;

                        if ($id === null) {
                            return null;
                        }

                        return TelegramFileAsset::query()->find($id)?->label();
                    })
                    ->addActionLabel('Add file')
                    ->helperText('Delivered by Telegram file_id in this order. Add files to the vault by DMing the bot as a file admin.')
                    ->columnSpanFull(),
                Select::make('learning_resource_id')
                    ->label('Learning resource')
                    ->options(fn (): array => LearningResource::query()
                        ->published()
                        ->orderBy('title')
                        ->limit(200)
                        ->pluck('title', 'id')
                        ->all())
                    ->getSearchResultsUsing(fn (string $search): array => LearningResource::query()
                        ->published()
                        ->when(filled($search), fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
                        ->orderBy('title')
                        ->limit(50)
                        ->pluck('title', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => LearningResource::query()->find($value)?->title)
                    ->searchable()
                    ->helperText('Uses the same delivery and premium rules as opening the resource in chat.'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
