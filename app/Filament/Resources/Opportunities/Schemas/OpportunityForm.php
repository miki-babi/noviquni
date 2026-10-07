<?php

namespace App\Filament\Resources\Opportunities\Schemas;

use App\Enums\OpportunityType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
            ]);
    }
}
