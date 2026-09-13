<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                TextInput::make('telegram_id')->disabled(),
                TextInput::make('telegram_username')->disabled(),
                TextInput::make('referral_code')->disabled(),
                Select::make('stream_id')->relationship('stream', 'name')->searchable()->preload(),
                Select::make('university_id')->relationship('university', 'name')->searchable()->preload(),
                Select::make('semester_id')->relationship('semester', 'name')->searchable()->preload(),
                Select::make('courses')
                    ->relationship('courses', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                DateTimePicker::make('premium_until'),
                Toggle::make('is_active')->default(true),
                Toggle::make('notifications_enabled')->default(true),
            ]);
    }
}
