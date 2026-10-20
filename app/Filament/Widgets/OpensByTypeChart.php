<?php

namespace App\Filament\Widgets;

use App\Enums\ResourceType;
use App\Models\ResourceDownload;
use Filament\Widgets\ChartWidget;

class OpensByTypeChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Opens by type';

    protected ?string $maxHeight = '280px';

    public ?string $filter = '30';

    /**
     * @return array<scalar, scalar>
     */
    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
            'all' => 'All time',
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $counts = ResourceDownload::query()
            ->join('learning_resources', 'resource_downloads.learning_resource_id', '=', 'learning_resources.id')
            ->when(
                $this->filter !== 'all',
                fn ($query) => $query->where('resource_downloads.created_at', '>=', now()->subDays((int) ($this->filter ?? 30))),
            )
            ->selectRaw('learning_resources.type, COUNT(*) as aggregate')
            ->groupBy('learning_resources.type')
            ->pluck('aggregate', 'type');

        $labels = [];
        $data = [];

        foreach (ResourceType::cases() as $type) {
            $count = (int) ($counts[$type->value] ?? 0);

            if ($count === 0) {
                continue;
            }

            $labels[] = $type->label();
            $data[] = $count;
        }

        // Bucket any legacy/unmapped type values so totals still add up.
        $mapped = array_sum($data);
        $total = (int) $counts->sum();
        if ($total > $mapped) {
            $labels[] = 'Other';
            $data[] = $total - $mapped;
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => ['#f59e0b', '#38bdf8', '#a78bfa', '#34d399', '#f87171'],
                ],
            ],
            'labels' => $labels,
        ];
    }
}
