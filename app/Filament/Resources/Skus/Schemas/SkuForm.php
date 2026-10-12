<?php

namespace App\Filament\Resources\Skus\Schemas;

use App\Models\Product;
use App\Models\Sku;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SkuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),

                TextInput::make('sku_code')
                    ->label('SKU code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('Must match the SKU on Shopify and TikTok Shop'),

                TextInput::make('barcode')
                    ->helperText('Optional for now'),

                TextInput::make('cost_price')
                    ->numeric()
                    ->prefix('RM')
                    ->default(0),

                TextInput::make('selling_price')
                    ->numeric()
                    ->prefix('RM')
                    ->helperText('Leave empty if TBC'),

                TextInput::make('reorder_level')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->helperText('Low-stock alert when stock falls to this number'),

                TextInput::make('safety_buffer')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->helperText('Units held back from Shopify and TikTok Shop'),

                Select::make('packing_mode')
                    ->options([
                        'pre_packed' => 'Pre-packed (finished bundles on the shelf)',
                        'on_demand'  => 'On demand (packed when an order comes in)',
                    ])
                    ->helperText('Bundles only. Leave empty for single products.'),

                Select::make('status')
                    ->options([
                        'active'   => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active')
                    ->required(),

                Repeater::make('components')
                    ->label('Bundle contents')
                    ->relationship()
                    ->schema([
                        Select::make('component_sku_id')
                            ->label('Component')
                            ->relationship(
                                'componentSku',
                                'sku_code',
                                fn (Builder $query) => $query->whereHas(
                                    'product',
                                    fn (Builder $q) => $q->where('type', '!=', 'bundle')
                                ),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Sku $record) => "{$record->sku_code} — {$record->product->name}")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                        TextInput::make('qty')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->addActionLabel('Add component')
                    ->visible(fn (Get $get): bool => Product::find($get('product_id'))?->type === 'bundle'),
            ]);
    }
}
