<?php

namespace App\Filament\Resources\OpportunityGuidanceRequests\Pages;

use App\Filament\Actions\AssignGuidanceRequestAction;
use App\Filament\Actions\SendGuidanceRequestTelegramMessageAction;
use App\Filament\Resources\OpportunityGuidanceRequests\OpportunityGuidanceRequestResource;
use App\Models\OpportunityGuidanceRequest;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewOpportunityGuidanceRequest extends ViewRecord
{
    protected static string $resource = OpportunityGuidanceRequestResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var OpportunityGuidanceRequest $record */
        $record = $this->getRecord();

        $title = $record->opportunity?->title;

        return filled($title) ? 'Guidance: '.$title : 'Guidance request';
    }

    protected function getHeaderActions(): array
    {
        return [
            AssignGuidanceRequestAction::make(),
            SendGuidanceRequestTelegramMessageAction::make(),
            DeleteAction::make()
                ->successRedirectUrl(OpportunityGuidanceRequestResource::getUrl('index')),
        ];
    }
}
