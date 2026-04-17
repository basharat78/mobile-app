<?php

namespace App\Filament\Widgets;

use App\Models\Carrier;
use App\Models\Load;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Carriers', Carrier::count())
                ->description('Total registered fleet')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),
            Stat::make('Pending Assignments', Carrier::whereNull('dispatcher_id')->count())
                ->description('Carriers needing a dispatcher')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('warning'),
            Stat::make('Active Loads', Load::whereIn('status', ['available', 'assigned'])->count())
                ->description('Current marketplace volume')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('success'),
        ];
    }
}
