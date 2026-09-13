<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class ViewActivityLogs extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Activity logs';

    protected static ?string $title = 'Activity logs';

    protected string $view = 'filament.pages.view-activity-logs';

    public function table(Table $table): Table
    {
        return $table
            ->query(ActivityLog::query()->latest())
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('User'),
                TextColumn::make('action')->searchable(),
                TextColumn::make('subject_type')->toggleable(),
                TextColumn::make('meta')->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : $state)->wrap(),
            ])
            ->paginated([25, 50, 100]);
    }
}
