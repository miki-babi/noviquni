<?php

namespace App\Filament\Resources\Learnings;

use App\Filament\Resources\Learnings\Pages\CreateLearning;
use App\Filament\Resources\Learnings\Pages\EditLearning;
use App\Filament\Resources\Learnings\Pages\ListLearnings;
use App\Filament\Resources\Learnings\Schemas\LearningForm;
use App\Filament\Resources\Learnings\Tables\LearningsTable;
use App\Models\LearningResource as LearningResourceModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LearningResource extends Resource
{
    protected static ?string $model = LearningResourceModel::class;

    protected static ?string $modelLabel = 'Learning resource';

    protected static ?string $pluralModelLabel = 'Learning resources';

    protected static ?string $navigationLabel = 'Resources';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return LearningForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LearningsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLearnings::route('/'),
            'create' => CreateLearning::route('/create'),
            'edit' => EditLearning::route('/{record}/edit'),
        ];
    }
}
