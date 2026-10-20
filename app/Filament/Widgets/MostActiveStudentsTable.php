<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MostActiveStudentsTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Most active students')
            ->query(
                User::query()
                    ->students()
                    ->has('downloads')
                    ->withCount([
                        'downloads',
                        'downloads as opens_7d' => fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(7)),
                        'downloads as opens_30d' => fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30)),
                        'downloads as resources_count' => fn (Builder $query) => $query->select(DB::raw('COUNT(DISTINCT learning_resource_id)')),
                    ])
                    ->withMax('downloads', 'created_at')
                    ->orderByDesc('opens_30d')
                    ->orderByDesc('downloads_count'),
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Student')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('telegram_username')
                    ->label('Telegram')
                    ->prefix('@')
                    ->placeholder('—'),
                TextColumn::make('university.name')
                    ->label('University')
                    ->placeholder('—'),
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
                TextColumn::make('resources_count')
                    ->label('Resources')
                    ->sortable(),
                TextColumn::make('downloads_max_created_at')
                    ->label('Last studied')
                    ->dateTime()
                    ->sortable(),
            ])
            ->paginated([10, 25, 50]);
    }
}
