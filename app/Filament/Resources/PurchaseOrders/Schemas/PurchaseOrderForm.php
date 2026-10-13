<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Models\PurchaseOrder;
use App\Models\Sku;
use App\Models\Supplier;
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

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        // Only drafts can be edited
        $locked = fn (?PurchaseOrder $record): bool => $record !== null && ! $record->isDraft();

        return $schema->components([

            Section::make('Purchase order')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('po_no')
                        ->label('PO no.')
                        ->visibleOn('edit'),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => PurchaseOrder::STATUSES[$state] ?? (string) $state)
                        ->color(fn (?string $state): string => self::statusColor($state))
                        ->visibleOn('edit'),
                    TextEntry::make('total')
                        ->money(fn (?PurchaseOrder $record): string => $record?->currency ?? 'MYR')
                        ->visibleOn('edit'),
                    TextEntry::make('approver.name')
                        ->label('Approved by')
                        ->visible(fn (?PurchaseOrder $record): bool => $record?->approved_at !== null),
                    TextEntry::make('approved_at')
                        ->label('Approved at')
                        ->dateTime('d M Y, H:i')
                        ->visible(fn (?PurchaseOrder $record): bool => $record?->approved_at !== null),

                    Select::make('supplier_id')
                        ->label('Supplier')
                        ->relationship(
                            name: 'supplier',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true),
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            $supplier = Supplier::find($state);
                            if ($supplier) {
                                $set('currency', $supplier->currency);
                            }
                        })
                        ->disabled($locked),
                    DatePicker::make('order_date')
                        ->label('PO date')
                        ->default(today())
                        ->required()
                        ->disabled($locked),
                    DatePicker::make('expected_date')
                        ->label('Expected delivery')
                        ->disabled($locked),
                    Select::make('currency')
                        ->options([
                            'MYR' => 'MYR',
                            'USD' => 'USD',
                            'SGD' => 'SGD',
                            'EUR' => 'EUR',
                        ])
                        ->default('MYR')
                        ->required()
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
                                    // Bundles are never purchased — buy their components instead
                                    modifyQueryUsing: fn (Builder $query) => $query
                                        ->with('product')
                                        ->whereHas('product', fn (Builder $q) => $q->where('type', '!=', 'bundle')),
                                )
                                ->getOptionLabelFromRecordUsing(fn (Sku $record): string => "{$record->sku_code} — {$record->product?->name}")
                                ->searchable(['sku_code'])
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn ($state, Set $set) => $set('unit_cost', Sku::find($state)?->cost_price ?? 0))
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->columnSpan(2),
                            TextInput::make('qty_ordered')
                                ->label('Qty')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->default(1)
                                ->required(),
                            TextInput::make('unit_cost')
                                ->label('Unit cost')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                        ])
                        ->columns(4)
                        ->defaultItems(1)
                        ->minItems(1)
                        ->addActionLabel('Add item')
                        ->disabled($locked),
                ]),

            Section::make('Charges, terms & notes')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('shipping_fee')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->disabled($locked),
                    TextInput::make('tax')
                        ->label('Tax (SST etc.)')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->disabled($locked),
                    Textarea::make('terms')
                        ->label('Terms (printed on PO)')
                        ->rows(3)
                        ->disabled($locked),
                    Textarea::make('notes')
                        ->label('Internal notes')
                        ->rows(3)
                        ->disabled($locked),
                ]),

            Section::make('Receiving')
                ->description('What has arrived so far, and into which batches.')
                ->columnSpanFull()
                ->visible(fn (?PurchaseOrder $record): bool => $record !== null && in_array($record->status, [
                    PurchaseOrder::STATUS_ORDERED,
                    PurchaseOrder::STATUS_PARTIAL,
                    PurchaseOrder::STATUS_RECEIVED,
                ], true))
                ->schema([
                    TextEntry::make('receiving_summary')
                        ->hiddenLabel()
                        ->state(fn (?PurchaseOrder $record): array => $record
                            ? $record->items()->with(['sku', 'batches'])->get()
                                ->map(fn ($item) => "{$item->sku->sku_code} — {$item->qty_received} / {$item->qty_ordered} received"
                                    . ($item->batches->isNotEmpty() ? ' · batch ' . $item->batches->pluck('batch_no')->join(', ') : ''))
                                ->all()
                            : [])
                        ->listWithLineBreaks()
                        ->bulleted(),
                ]),
        ]);
    }

    public static function statusColor(?string $state): string
    {
        return match ($state) {
            PurchaseOrder::STATUS_SUBMITTED => 'warning',
            PurchaseOrder::STATUS_APPROVED => 'info',
            PurchaseOrder::STATUS_ORDERED => 'primary',
            PurchaseOrder::STATUS_PARTIAL => 'warning',
            PurchaseOrder::STATUS_RECEIVED => 'success',
            PurchaseOrder::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }
}