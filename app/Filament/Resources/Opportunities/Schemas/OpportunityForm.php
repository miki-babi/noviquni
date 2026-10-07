<?php

namespace App\Filament\Resources\Opportunities\Schemas;

use App\Enums\OpportunityType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class OpportunityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? '')))
                    ->columnSpanFull(),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('type')
                    ->options(collect(OpportunityType::cases())->mapWithKeys(
                        fn (OpportunityType $type) => [$type->value => $type->label()]
                    )->all())
                    ->required()
                    ->native(false),
                Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('url')
                    ->label('Apply / open URL')
                    ->url()
                    ->maxLength(2048)
                    ->columnSpanFull(),
                DatePicker::make('deadline')
                    ->native(false),
                TextInput::make('sort_order')
                    ->numeric()
                    ->integer()
                    ->default(0)
                    ->required(),
                Toggle::make('is_published')
                    ->default(false),
                Toggle::make('is_verified_partner')
                    ->label('Verified NOViQ Uni partner')
                    ->default(false)
                    ->live(),
                TextInput::make('partner_name')
                    ->label('Partner name')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => (bool) $get('is_verified_partner'))
                    ->required(fn (Get $get): bool => (bool) $get('is_verified_partner'))
                    ->columnSpanFull(),
                TextInput::make('guidance_contact_username')
                    ->label('Guidance contact username')
                    ->helperText('Optional. Leave blank to use Settings default. Without @.')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => (bool) $get('is_verified_partner'))
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? ltrim(trim($state), '@') : null)
                    ->columnSpanFull(),
                Textarea::make('guidance_opening_message')
                    ->label('Guidance opening message')
                    ->helperText('Optional. Leave blank to use Settings default. Placeholders: {title}, {partner}, {student}')
                    ->rows(4)
                    ->visible(fn (Get $get): bool => (bool) $get('is_verified_partner'))
                    ->columnSpanFull(),
            ]);
    }
}
