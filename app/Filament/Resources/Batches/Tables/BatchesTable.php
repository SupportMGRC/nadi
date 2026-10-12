<?php

namespace App\Filament\Resources\Batches\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku.sku_code')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sku.product.name')
                    ->label('Product'),

                TextColumn::make('batch_no')
                    ->label('Batch no.')
                    ->searchable(),

                TextColumn::make('received_date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('expiry_date')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn ($state) => $state && $state->lte(now()->addDays(60)) ? 'danger' : null),

                TextColumn::make('supplier')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('received_date', 'asc')
            ->filters([
                SelectFilter::make('sku')
                    ->relationship('sku', 'sku_code'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}