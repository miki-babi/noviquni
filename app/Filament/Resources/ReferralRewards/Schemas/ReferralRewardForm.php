<?php

namespace App\Filament\Resources\ReferralRewards\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ReferralRewardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')->relationship('user', 'name')->disabled(),
                TextInput::make('amount')->numeric()->disabled(),
                TextInput::make('status')->disabled(),
            ]);
    }
}
