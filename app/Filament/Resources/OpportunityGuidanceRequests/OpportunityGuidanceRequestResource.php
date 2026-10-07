<?php

namespace App\Filament\Resources\OpportunityGuidanceRequests;

use App\Filament\Resources\OpportunityGuidanceRequests\Pages\ListOpportunityGuidanceRequests;
use App\Filament\Resources\OpportunityGuidanceRequests\Pages\ViewOpportunityGuidanceRequest;
use App\Filament\Resources\OpportunityGuidanceRequests\Schemas\OpportunityGuidanceRequestInfolist;
use App\Filament\Resources\OpportunityGuidanceRequests\Tables\OpportunityGuidanceRequestsTable;
use App\Models\OpportunityGuidanceRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OpportunityGuidanceRequestResource extends Resource
{
    protected static ?string $model = OpportunityGuidanceRequest::class;

    protected static ?string $modelLabel = 'Guidance request';

    protected static ?string $pluralModelLabel = 'Guidance requests';

    protected static ?string $navigationLabel = 'Guidance requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'opportunity', 'assignee']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OpportunityGuidanceRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OpportunityGuidanceRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpportunityGuidanceRequests::route('/'),
            'view' => ViewOpportunityGuidanceRequest::route('/{record}'),
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
}
