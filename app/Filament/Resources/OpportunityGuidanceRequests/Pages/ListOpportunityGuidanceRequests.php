<?php

namespace App\Filament\Resources\OpportunityGuidanceRequests\Pages;

use App\Filament\Resources\OpportunityGuidanceRequests\OpportunityGuidanceRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListOpportunityGuidanceRequests extends ListRecords
{
    protected static string $resource = OpportunityGuidanceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
