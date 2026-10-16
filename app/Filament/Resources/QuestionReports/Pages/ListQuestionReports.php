<?php

namespace App\Filament\Resources\QuestionReports\Pages;

use App\Filament\Resources\QuestionReports\QuestionReportResource;
use Filament\Resources\Pages\ListRecords;

class ListQuestionReports extends ListRecords
{
    protected static string $resource = QuestionReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
