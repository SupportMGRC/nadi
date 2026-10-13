<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockSkus extends TableWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low stock')
            ->description('SKUs at or below reorder level (bundles excluded)')
            ->query(fn (): Builder => app(DashboardService::class)
                ->lowStockSkus()
                ->with('product')
                ->orderByRaw('on_hand asc'))
            ->columns([
                TextColumn::make('sku_code')
                    ->label('SKU')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('product.name')
                    ->label('Product'),

                TextColumn::make('on_hand')
                    ->label('On hand')
                    ->numeric()
                    ->badge()
                    ->color(fn ($state): string => (int) $state <= 0 ? 'danger' : 'warning'),

                TextColumn::make('reorder_level')
                    ->label('Reorder level')
                    ->numeric(),

                TextColumn::make('shortfall')
                    ->label('Short by')
                    ->state(fn ($record): int => max(0, (int) $record->reorder_level - (int) $record->on_hand))
                    ->numeric(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No low stock')
            ->emptyStateDescription('All SKUs are above their reorder level.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}