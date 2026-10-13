<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\Actions\OrderActions;
use App\Models\Order;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('order_no')
                    ->label('Order no.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('external_ref')
                    ->label('Shopify / Tiktok no.')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('order_date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Order::CHANNELS[$state] ?? $state),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items'),
                TextColumn::make('total')
                    ->money('MYR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Order::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Order::STATUS_CONFIRMED => 'success',
                        Order::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options(Order::STATUSES),
                SelectFilter::make('channel')->options(Order::CHANNELS),
            ])
            ->recordActions([
                OrderActions::confirm(),
                OrderActions::cancel(),
                EditAction::make()
                    ->label(fn (Order $record): string => $record->isDraft() ? 'Edit' : 'View'),
            ]);
    }
}