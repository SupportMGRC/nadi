<?php

namespace App\Filament\Resources\Skus\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SkusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
    TextColumn::make('sku_code')
        ->label('SKU')
        ->searchable()
        ->sortable(),

    TextColumn::make('product.name')
        ->label('Product')
        ->searchable()
        ->sortable(),

    TextColumn::make('selling_price')
        ->money('MYR')
        ->placeholder('TBC'),

    TextColumn::make('reorder_level')
        ->label('Reorder at')
        ->numeric(),

    TextColumn::make('status')
        ->badge()
        ->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger'),
])
            ->filters([
                //
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
