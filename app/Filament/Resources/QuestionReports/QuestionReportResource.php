<?php

namespace App\Filament\Resources\QuestionReports;

use App\Filament\Resources\QuestionReports\Pages\ListQuestionReports;
use App\Filament\Resources\QuestionReports\Pages\ViewQuestionReport;
use App\Filament\Resources\QuestionReports\Schemas\QuestionReportInfolist;
use App\Filament\Resources\QuestionReports\Tables\QuestionReportsTable;
use App\Models\QuestionReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class QuestionReportResource extends Resource
{
    protected static ?string $model = QuestionReport::class;

    protected static ?string $modelLabel = 'Question report';

    protected static ?string $pluralModelLabel = 'Question reports';

    protected static ?string $navigationLabel = 'Question reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'learningResource', 'dailyChallenge', 'resolver']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return QuestionReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuestionReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuestionReports::route('/'),
            'view' => ViewQuestionReport::route('/{record}'),
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
