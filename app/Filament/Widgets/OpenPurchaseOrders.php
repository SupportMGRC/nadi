<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Services\DashboardService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class OpenPurchaseOrders extends TableWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Open purchase orders')
            ->description('Submitted, approved, ordered or partially received — earliest expected first')
            ->query(fn (): Builder => app(DashboardService::class)
                ->openPurchaseOrders()
                ->with('supplier')
                ->orderByRaw('expected_date is null, expected_date asc'))
            ->columns([
                TextColumn::make('po_no')
                    ->label('PO no.')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('supplier.name')
                    ->label('Supplier'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->color(fn (string $state): string => match ($state) {
                        'submitted'          => 'gray',
                        'approved'           => 'info',
                        'ordered'            => 'primary',
                        'partially_received' => 'warning',
                        default              => 'gray',
                    }),

                TextColumn::make('order_date')
                    ->label('Order date')
                    ->date('d M Y')
                    ->placeholder('—'),

                TextColumn::make('expected_date')
                    ->label('Expected')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->color(fn ($record): ?string => $record->expected_date
                        && Carbon::parse($record->expected_date)->startOfDay()->lt(today())
                            ? 'danger'
                            : null)
                    ->description(fn ($record): ?string => $record->expected_date
                        && Carbon::parse($record->expected_date)->startOfDay()->lt(today())
                            ? 'Overdue'
                            : null),

                TextColumn::make('total')
                    ->money(fn ($record): string => $record->currency ?: 'MYR'),
            ])
            ->recordUrl(fn ($record): string => PurchaseOrderResource::getUrl('edit', ['record' => $record]))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No open purchase orders')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}