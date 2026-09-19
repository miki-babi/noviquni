<?php

namespace App\Filament\Resources\Universities\Schemas;

use App\Filament\Forms\Components\SeoFields;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class UniversityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('location')
                    ->placeholder('Addis Ababa, Ethiopia'),
                TextInput::make('website')
                    ->url()
                    ->maxLength(255),
                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->image()
                    ->disk(config('filesystems.default'))
                    ->directory('universities/logos')
                    ->preserveFilenames()
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->default(true),
                ...SeoFields::make(),
            ]);
    }
}
