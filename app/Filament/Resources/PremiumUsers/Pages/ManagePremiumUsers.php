<?php

namespace App\Filament\Resources\PremiumUsers\Pages;

use App\Filament\Resources\PremiumUsers\PremiumUserResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePremiumUsers extends ManageRecords
{
    protected static string $resource = PremiumUserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
