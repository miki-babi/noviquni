<?php

namespace App\Filament\Resources\PremiumUsers;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\PremiumUsers\Pages\ManagePremiumUsers;
use App\Filament\Resources\PremiumUsers\Tables\PremiumUsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PremiumUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Premium user';

    protected static ?string $pluralModelLabel = 'Premium users';

    protected static ?string $slug = 'premium-users';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Monetization';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('role', UserRole::Student)
            ->where('is_premium', true)
            ->with([
                'subscriptions' => fn ($query) => $query->latest('id'),
                'payments' => fn ($query) => $query
                    ->where('status', PaymentStatus::Verified)
                    ->latest('id'),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return PremiumUsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePremiumUsers::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
