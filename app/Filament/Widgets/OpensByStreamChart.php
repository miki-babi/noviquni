<?php

namespace App\Filament\Widgets;

use App\Models\ResourceDownload;
use Filament\Widgets\ChartWidget;

class OpensByStreamChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Top streams by opens';

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
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = ResourceDownload::query()
            ->join('learning_resources', 'resource_downloads.learning_resource_id', '=', 'learning_resources.id')
            ->join('streams', 'learning_resources.stream_id', '=', 'streams.id')
            ->when(
                $this->filter !== 'all',
                fn ($query) => $query->where('resource_downloads.created_at', '>=', now()->subDays((int) ($this->filter ?? 30))),
            )
            ->selectRaw('streams.name, COUNT(*) as aggregate')
            ->groupBy('streams.id', 'streams.name')
            ->orderByDesc('aggregate')
            ->limit(8)
            ->pluck('aggregate', 'name');

        return [
            'datasets' => [
                [
                    'label' => 'Resource opens',
                    'data' => $rows->values()->all(),
                ],
            ],
            'labels' => $rows->keys()->all(),
        ];
    }
}
