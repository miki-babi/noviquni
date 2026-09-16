<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

class SeoFields
{
    /**
     * @return list<Section>
     */
    public static function make(): array
    {
        return [
            Section::make('SEO')
                ->columns(2)
                ->schema([
                    TextInput::make('seo_title')
                        ->label('SEO title')
                        ->maxLength(70)
                        ->columnSpanFull(),
                    Textarea::make('seo_description')
                        ->label('Meta description')
                        ->rows(3)
                        ->maxLength(160)
                        ->columnSpanFull(),
                    Textarea::make('seo_content')
                        ->label('SEO content')
                        ->helperText('Original page body shown on the public site. Keep it useful and unique.')
                        ->rows(8)
                        ->columnSpanFull(),
                    FileUpload::make('og_image')
                        ->label('Open Graph image')
                        ->image()
                        ->disk(config('filesystems.default'))
                        ->directory('seo/og')
                        ->columnSpanFull(),
                    Toggle::make('is_indexable')
                        ->label('Indexable')
                        ->helperText('When off, the public page uses noindex and is omitted from the sitemap.')
                        ->default(true),
                ])
                ->collapsed(),
        ];
    }
}
