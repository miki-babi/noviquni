<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresSuperAdmin;
use App\Filament\Widgets\MostActiveStudentsTable;
use App\Filament\Widgets\OpensByStreamChart;
use App\Filament\Widgets\OpensByTypeChart;
use App\Filament\Widgets\OpensByUniversityChart;
use App\Filament\Widgets\OpensTrendChart;
use App\Filament\Widgets\StudyInsightsStats;
use App\Filament\Widgets\TopStudiedCoursesTable;
use App\Filament\Widgets\TopStudiedResourcesTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class StudyInsights extends Page
{
    use RequiresSuperAdmin;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Study insights';

    protected static ?string $title = 'Study insights';

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            StudyInsightsStats::class,
        ];
    }

    /**
     * @return int|array<string, int|null>
     */
    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    /**
     * @return array<class-string>
     */
    protected function getFooterWidgets(): array
    {
        return [
            OpensTrendChart::class,
            OpensByTypeChart::class,
            OpensByStreamChart::class,
            OpensByUniversityChart::class,
            TopStudiedResourcesTable::class,
            TopStudiedCoursesTable::class,
            MostActiveStudentsTable::class,
        ];
    }

    /**
     * @return int|array<string, int|null>
     */
    public function getFooterWidgetsColumns(): int|array
    {
        return ['md' => 2, 'xl' => 3];
    }
}
