<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')->relationship('user', 'name')->disabled(),
                TextInput::make('amount')->numeric()->disabled(),
                TextInput::make('currency')->disabled(),
                TextInput::make('external_ref')->disabled(),
                TextInput::make('status')->disabled(),
                TextInput::make('purpose')->disabled(),
            ]);
    }
}
