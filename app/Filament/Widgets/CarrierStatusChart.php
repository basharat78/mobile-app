<?php

namespace App\Filament\Widgets;

use App\Models\Carrier;
use Filament\Widgets\ChartWidget;

class CarrierStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Carrier Status Breakdown';

    protected static ?int $sort = 4;

    protected static ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $approved = Carrier::where('status', 'approved')->count();
        $pending = Carrier::where('status', 'pending')->count();
        $rejected = Carrier::where('status', 'rejected')->count();

        return [
            'datasets' => [
                [
                    'label' => 'Carriers',
                    'data' => [$approved, $pending, $rejected],
                    'backgroundColor' => [
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => ['Approved', 'Pending', 'Rejected'],
        ];
    }
}
