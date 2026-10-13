<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class NearExpiryBatches extends TableWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Near-expiry batches')
            ->description('Batches with stock expiring within ' . DashboardService::EXPIRY_WARNING_DAYS . ' days, or already expired')
            ->query(fn (): Builder => app(DashboardService::class)
                ->nearExpiryBatches()
                ->with('sku.product'))
            ->columns([
                TextColumn::make('sku.sku_code')
                    ->label('SKU')
                    ->weight('bold'),

                TextColumn::make('sku.product.name')
                    ->label('Product'),

                TextColumn::make('batch_no')
                    ->label('Batch')
                    ->searchable(),

                TextColumn::make('expiry_date')
                    ->label('Expiry')
                    ->date('d M Y'),

                TextColumn::make('days_left')
                    ->label('Days left')
                    ->state(fn ($record): int => (int) now()->startOfDay()
                        ->diffInDays(Carbon::parse($record->expiry_date)->startOfDay(), false))
                    ->formatStateUsing(fn (int $state): string => match (true) {
                        $state < 0   => 'Expired ' . abs($state) . ' day(s) ago',
                        $state === 0 => 'Expires today',
                        default      => $state . ' day(s)',
                    })
                    ->badge()
                    ->color(fn (int $state): string => $state <= 0 ? 'danger' : ($state <= 30 ? 'warning' : 'gray')),

                TextColumn::make('on_hand')
                    ->label('On hand')
                    ->numeric(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No batches near expiry')
            ->emptyStateDescription('Nothing with stock expires in the next ' . DashboardService::EXPIRY_WARNING_DAYS . ' days.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}