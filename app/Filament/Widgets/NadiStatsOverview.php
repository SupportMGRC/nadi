<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NadiStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $dashboard = app(DashboardService::class);

        $todaysOrders = $dashboard->todaysOrders()->count();
        $lowStock     = $dashboard->lowStockSkus()->count();
        $nearExpiry   = $dashboard->nearExpiryBatches()->count();
        $openPos      = $dashboard->openPurchaseOrders()->count();

        return [
            Stat::make("Today's orders", $todaysOrders)
                ->description('Excluding cancelled')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('primary'),

            Stat::make('Low stock SKUs', $lowStock)
                ->description($lowStock > 0 ? 'At or below reorder level' : 'All above reorder level')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStock > 0 ? 'danger' : 'success'),

            Stat::make('Batches expiring ≤ ' . DashboardService::EXPIRY_WARNING_DAYS . ' days', $nearExpiry)
                ->description('Still have stock')
                ->descriptionIcon('heroicon-m-clock')
                ->color($nearExpiry > 0 ? 'warning' : 'success'),

            Stat::make('Open purchase orders', $openPos)
                ->description('Submitted → partially received')
                ->descriptionIcon('heroicon-m-truck')
                ->color('gray'),
        ];
    }
}