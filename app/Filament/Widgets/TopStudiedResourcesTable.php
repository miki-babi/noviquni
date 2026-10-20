<?php

namespace App\Filament\Widgets;

use App\Models\LearningResource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopStudiedResourcesTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Most opened resources')
            ->query(
                LearningResource::query()
                    ->withCount([
                        'downloads',
                        'downloads as opens_7d' => fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(7)),
                        'downloads as opens_30d' => fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30)),
                        'downloads as students_count' => fn (Builder $query) => $query->select(DB::raw('COUNT(DISTINCT user_id)')),
                    ])
                    ->withMax('downloads', 'created_at')
                    ->orderByDesc('opens_30d')
                    ->orderByDesc('downloads_count'),
            )
            ->columns([
                TextColumn::make('title')
                    ->label('Resource')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('course.name')
                    ->label('Course')
                    ->placeholder('—'),
                TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
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
                IconColumn::make('is_premium')
                    ->label('Premium')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated([10, 25, 50]);
    }
}
