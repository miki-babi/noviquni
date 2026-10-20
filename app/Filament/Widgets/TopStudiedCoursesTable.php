<?php

namespace App\Filament\Widgets;

use App\Models\Course;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopStudiedCoursesTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top courses by opens')
            ->query(
                Course::query()
                    ->withCount([
                        'downloads',
                        'downloads as opens_7d' => fn (Builder $query) => $query->where('resource_downloads.created_at', '>=', now()->subDays(7)),
                        'downloads as opens_30d' => fn (Builder $query) => $query->where('resource_downloads.created_at', '>=', now()->subDays(30)),
                        'downloads as students_count' => fn (Builder $query) => $query->select(DB::raw('COUNT(DISTINCT user_id)')),
                    ])
                    ->withMax('downloads', 'created_at')
                    ->orderByDesc('opens_30d')
                    ->orderByDesc('downloads_count'),
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Course')
                    ->searchable(),
                TextColumn::make('stream.name')
                    ->label('Stream')
                    ->placeholder('—'),
                TextColumn::make('opens_7d')
                    ->label('Opens 7d')
                    ->sortable(),
                TextColumn::make('opens_30d')
                    ->label('Opens 30d')
                    ->sortable(),
                TextColumn::make('downloads_count')
                    ->label('Opens all')
                    ->sortable(),
                TextColumn::make('students_count')
                    ->label('Students')
                    ->sortable(),
                TextColumn::make('downloads_max_created_at')
                    ->label('Last opened')
                    ->dateTime()
                    ->sortable(),
            ])
            ->paginated([10, 25, 50]);
    }
}
