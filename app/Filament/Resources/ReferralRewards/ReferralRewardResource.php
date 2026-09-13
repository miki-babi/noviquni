<?php

namespace App\Filament\Resources\ReferralRewards;

use App\Filament\Resources\ReferralRewards\Pages\ListReferralRewards;
use App\Filament\Resources\ReferralRewards\Schemas\ReferralRewardForm;
use App\Filament\Resources\ReferralRewards\Tables\ReferralRewardsTable;
use App\Models\ReferralReward;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ReferralRewardResource extends Resource
{
    protected static ?string $model = ReferralReward::class;

    protected static ?string $navigationLabel = 'Rewards';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Monetization';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ReferralRewardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferralRewardsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferralRewards::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
