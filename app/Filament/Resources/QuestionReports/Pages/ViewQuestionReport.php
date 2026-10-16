<?php

namespace App\Filament\Resources\QuestionReports\Pages;

use App\Filament\Actions\DismissQuestionReportAction;
use App\Filament\Actions\ResolveQuestionReportAction;
use App\Filament\Resources\QuestionReports\QuestionReportResource;
use App\Models\QuestionReport;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewQuestionReport extends ViewRecord
{
    protected static string $resource = QuestionReportResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var QuestionReport $record */
        $record = $this->getRecord();

        return filled($record->question_label)
            ? 'Report: '.$record->question_label
            : 'Question report';
    }

    protected function getHeaderActions(): array
    {
        return [
            ResolveQuestionReportAction::make(),
            DismissQuestionReportAction::make(),
        ];
    }
}
