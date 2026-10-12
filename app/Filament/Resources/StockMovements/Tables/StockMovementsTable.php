<?php

namespace App\Filament\Resources\StockMovements\Tables;

use App\Models\StockMovement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),

                TextColumn::make('sku.sku_code')
                    ->label('SKU')
                    ->searchable(),

                TextColumn::make('batch.batch_no')
                    ->label('Batch'),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => StockMovement::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'receive', 'return_restock', 'assemble_in' => 'success',
                        'sale', 'assemble_out'                     => 'info',
                        'damaged'                                  => 'danger',
                        default                                    => 'warning',
                    }),

                TextColumn::make('qty')
                    ->formatStateUsing(fn (int $state) => $state > 0 ? "+{$state}" : (string) $state)
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger')
                    ->weight('bold'),

                TextColumn::make('notes')
                    ->limit(40)
                    ->placeholder('—'),

                TextColumn::make('creator.name')
                    ->label('By')
                    ->placeholder('System'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(StockMovement::TYPES),

                SelectFilter::make('sku')
                    ->relationship('sku', 'sku_code'),
            ]);
    }
}