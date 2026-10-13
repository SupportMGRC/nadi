<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Models\Sku;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        // Confirmed / cancelled orders are read-only
        $locked = fn (?Order $record): bool => $record !== null && ! $record->isDraft();

        return $schema->components([

            Section::make('Order')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('order_no')
                        ->label('Order no.')
                        ->visibleOn('edit'),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => Order::STATUSES[$state] ?? (string) $state)
                        ->color(fn (?string $state): string => match ($state) {
                            Order::STATUS_CONFIRMED => 'success',
                            Order::STATUS_CANCELLED => 'danger',
                            default => 'gray',
                        })
                        ->visibleOn('edit'),
                    TextEntry::make('total')
                        ->money('MYR')
                        ->visibleOn('edit'),

                    Select::make('channel')
                        ->options(Order::CHANNELS)
                        ->default('manual')
                        ->required()
                        ->disabled($locked),
                    TextInput::make('external_ref')
                        ->label('External ref.')
                        ->placeholder('Shopify / TikTok order no.')
                        ->disabled($locked),
                    DatePicker::make('order_date')
                        ->default(today())
                        ->required()
                        ->disabled($locked),
                ]),

            Section::make('Customer')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('customer_name')
                        ->required()
                        ->disabled($locked),
                    TextInput::make('customer_phone')
                        ->tel()
                        ->disabled($locked),
                    TextInput::make('customer_email')
                        ->email()
                        ->disabled($locked),
                    Textarea::make('shipping_address')
                        ->rows(2)
                        ->columnSpanFull()
                        ->disabled($locked),
                ]),

            Section::make('Items')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->hiddenLabel()
                        ->schema([
                            Select::make('sku_id')
                                ->label('SKU')
                                ->relationship(
                                    name: 'sku',
                                    titleAttribute: 'sku_code',
                                    modifyQueryUsing: fn (Builder $query) => $query
                                        ->with('product')
                                        ->whereHas('product', fn (Builder $q) => $q->where('is_sellable', true)),
                                )
                                ->getOptionLabelFromRecordUsing(fn (Sku $record): string => "{$record->sku_code} — {$record->product?->name}")
                                ->searchable(['sku_code'])
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn ($state, Set $set) => $set('unit_price', Sku::find($state)?->selling_price ?? 0))
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->columnSpan(2),
                            TextInput::make('qty')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->default(1)
                                ->required(),
                            TextInput::make('unit_price')
                                ->label('Unit price')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('RM')
                                ->required(),
                        ])
                        ->columns(4)
                        ->defaultItems(1)
                        ->minItems(1)
                        ->addActionLabel('Add item')
                        ->disabled($locked),
                ]),

            Section::make('Charges & notes')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('shipping_fee')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('RM')
                        ->default(0)
                        ->disabled($locked),
                    TextInput::make('discount')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('RM')
                        ->default(0)
                        ->disabled($locked),
                    Textarea::make('notes')
                        ->rows(2)
                        ->columnSpanFull()
                        ->disabled($locked),
                ]),

            Section::make('Picked batches')
                ->description('Physical stock taken for this order (bundles shown as their components).')
                ->columnSpanFull()
                ->visible(fn (?Order $record): bool => $record !== null && ! $record->isDraft())
                ->schema([
                    TextEntry::make('picks_list')
                        ->hiddenLabel()
                        ->state(fn (?Order $record): array => $record
                            ? $record->picks()->with(['sku', 'batch'])->get()
                                ->map(fn ($p) => "{$p->sku->sku_code} — batch {$p->batch->batch_no} × {$p->qty}")
                                ->all()
                            : [])
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->placeholder('Nothing picked'),
                ]),
        ]);
    }
}