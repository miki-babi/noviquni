<?php

namespace App\Filament\Widgets;

use App\Models\LearningResource;
use App\Models\ResourceDownload;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StudyInsightsStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $opensToday = ResourceDownload::query()->whereDate('created_at', today())->count();
        $opens7d = ResourceDownload::query()->where('created_at', '>=', now()->subDays(7))->count();
        $opens30d = ResourceDownload::query()->where('created_at', '>=', now()->subDays(30))->count();
        $active7d = ResourceDownload::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->distinct()
            ->count('user_id');
        $active30d = ResourceDownload::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->distinct()
            ->count('user_id');
        $topResourceId = ResourceDownload::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->select('learning_resource_id')
            ->groupBy('learning_resource_id')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->value('learning_resource_id');
        $topResource = $topResourceId !== null
            ? LearningResource::query()->find($topResourceId)?->title
            : null;

        return [
            Stat::make('Opens today', (string) $opensToday),
            Stat::make('Opens (7d)', (string) $opens7d),
            Stat::make('Opens (30d)', (string) $opens30d),
            Stat::make('Active students (7d)', (string) $active7d)
                ->description('Distinct students who opened a resource'),
            Stat::make('Active students (30d)', (string) $active30d),
            Stat::make('Top resource (30d)', $topResource !== null ? (string) str($topResource)->limit(40) : '—')
                ->description('Most opened resource in the last 30 days'),
        ];
    }
}
