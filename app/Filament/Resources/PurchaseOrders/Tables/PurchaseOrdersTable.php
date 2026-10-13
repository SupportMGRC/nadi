<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Filament\Resources\PurchaseOrders\Actions\PurchaseOrderActions;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Models\PurchaseOrder;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('po_no')
                    ->label('PO no.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(),
                TextColumn::make('order_date')
                    ->label('PO date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('expected_date')
                    ->label('Expected')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items'),
                TextColumn::make('total')
                    ->money(fn (PurchaseOrder $record): string => $record->currency)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PurchaseOrder::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => PurchaseOrderForm::statusColor($state)),
            ])
            ->filters([
                SelectFilter::make('status')->options(PurchaseOrder::STATUSES),
                SelectFilter::make('supplier')->relationship('supplier', 'name'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(fn (PurchaseOrder $record): string => $record->isDraft() ? 'Edit' : 'View'),
                ActionGroup::make([
                    PurchaseOrderActions::submit(),
                    PurchaseOrderActions::approve(),
                    PurchaseOrderActions::returnToDraft(),
                    PurchaseOrderActions::markOrdered(),
                    PurchaseOrderActions::receive(),
                    PurchaseOrderActions::cancel(),
                ]),
            ]);
    }
}