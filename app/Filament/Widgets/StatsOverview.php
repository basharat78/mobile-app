<?php

namespace App\Filament\Widgets;

use App\Models\Carrier;
use App\Models\CarrierDocument;
use App\Models\Load;
use App\Models\LoadRequest;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalLoads = Load::count();
        $availableLoads = Load::where('status', 'available')->count();
        $assignedLoads = Load::where('status', 'assigned')->count();

        $totalCarriers = Carrier::count();
        $approvedCarriers = Carrier::where('status', 'approved')->count();
        $pendingCarriers = Carrier::where('status', 'pending')->count();

        $totalDispatchers = User::where('role', 'dispatcher')->count();
        $unassignedCarriers = Carrier::whereNull('dispatcher_id')->count();

        $pendingBids = LoadRequest::where('status', 'pending')->count();

        $pendingDocs = CarrierDocument::where('status', 'pending')->count();

        return [
            Stat::make('Total Loads Posted', $totalLoads)
                ->description("{$availableLoads} available · {$assignedLoads} assigned")
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('primary')
                ->chart(self::getLoadTrend()),

            Stat::make('Total Carriers', $totalCarriers)
                ->description("{$approvedCarriers} approved · {$pendingCarriers} pending")
                ->descriptionIcon('heroicon-m-truck')
                ->color('info')
                ->chart(self::getCarrierTrend()),

            Stat::make('Active Dispatchers', $totalDispatchers)
                ->description("{$unassignedCarriers} carriers unassigned")
                ->descriptionIcon('heroicon-m-user-group')
                ->color($unassignedCarriers > 0 ? 'warning' : 'success'),

            Stat::make('Pending Bids', $pendingBids)
                ->description('Awaiting dispatcher review')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingBids > 0 ? 'warning' : 'success'),

            Stat::make('Pending Documents', $pendingDocs)
                ->description('Awaiting review & approval')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($pendingDocs > 0 ? 'danger' : 'success'),
        ];
    }

    protected static function getLoadTrend(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $data[] = Load::whereDate('created_at', now()->subDays($i))->count();
        }
        return $data;
    }

    protected static function getCarrierTrend(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $data[] = Carrier::whereDate('created_at', now()->subDays($i))->count();
        }
        return $data;
    }

}
