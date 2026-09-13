<?php

namespace App\Filament\Resources\Learnings\Schemas;

use App\Enums\ResourceType;
use App\Models\Course;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class LearningForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->required()->columnSpanFull(),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                Select::make('type')
                    ->options(ResourceType::class)
                    ->required(),
                Select::make('stream_id')
                    ->relationship('stream', 'name')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('course_id', null))
                    ->searchable()
                    ->preload(),
                Select::make('course_id')
                    ->label('Course')
                    ->options(fn (Get $get): array => Course::query()
                        ->when($get('stream_id'), fn ($q, $streamId) => $q->where('stream_id', $streamId))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->searchable(),
                Select::make('university_id')
                    ->relationship('university', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('semester_id')
                    ->relationship('semester', 'name')
                    ->searchable()
                    ->preload(),
                Toggle::make('is_premium')->default(false),
                Toggle::make('is_published')->default(false),
                FileUpload::make('file_path')
                    ->label('File')
                    ->disk(config('filesystems.default'))
                    ->directory('learning-resources')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'image/jpeg',
                        'image/png',
                        'application/zip',
                    ])
                    ->maxSize(20480)
                    ->columnSpanFull(),
            ]);
    }
}
