<?php

namespace App\Filament\Widgets;

use App\Models\Load;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class DispatcherLoadsChart extends ChartWidget
{
    protected ?string $heading = 'Loads by Dispatcher';

    protected static ?int $sort = 3;

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $dispatchers = User::where('role', 'dispatcher')
            ->withCount('loads')
            ->orderByDesc('loads_count')
            ->limit(8)
            ->get();

        $labels = $dispatchers->pluck('name')->toArray();
        $data = $dispatchers->pluck('loads_count')->toArray();

        $colors = [
            'rgba(59, 130, 246, 0.8)',
            'rgba(16, 185, 129, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(239, 68, 68, 0.8)',
            'rgba(139, 92, 246, 0.8)',
            'rgba(236, 72, 153, 0.8)',
            'rgba(20, 184, 166, 0.8)',
            'rgba(249, 115, 22, 0.8)',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Loads',
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($data)),
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
