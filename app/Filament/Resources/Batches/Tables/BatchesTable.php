<?php

namespace App\Filament\Resources\Batches\Tables;

use App\Models\Batch;
use App\Services\StockService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use InvalidArgumentException;

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

                TextColumn::make('movements_sum_qty')
                    ->label('On hand')
                    ->sum('movements', 'qty')
                    ->numeric()
                    ->default(0)
                    ->weight('bold'),

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
                Action::make('receive')
                    ->label('Receive')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('success')
                    ->schema([
                        TextInput::make('qty')
                            ->label('Quantity received')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        Textarea::make('notes')
                            ->placeholder('e.g. PO number, delivery order no.'),
                    ])
                    ->action(function (Batch $record, array $data): void {
                        app(StockService::class)->receive($record, (int) $data['qty'], $data['notes'] ?? null);

                        Notification::make()
                            ->title("Received {$data['qty']} into {$record->batch_no}")
                            ->success()
                            ->send();
                    }),

                Action::make('adjust')
                    ->label('Adjust')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->color('warning')
                    ->schema([
                        TextInput::make('qty')
                            ->label('Change')
                            ->helperText('Use minus to remove, e.g. -2 for 2 damaged boxes. Plus to add, e.g. 5 after a recount.')
                            ->numeric()
                            ->integer()
                            ->required(),
                        Textarea::make('reason')
                            ->required(),
                    ])
                    ->action(function (Batch $record, array $data): void {
                        try {
                            app(StockService::class)->adjust($record, (int) $data['qty'], $data['reason']);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->title('Adjustment refused')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title("Adjusted {$record->batch_no} by {$data['qty']}")
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}