<?php

namespace App\Filament\Resources\Batches\Schemas;

use App\Models\Sku;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sku_id')
                    ->label('SKU')
                    ->relationship('sku', 'sku_code')
                    ->getOptionLabelFromRecordUsing(fn (Sku $record) => "{$record->sku_code} — {$record->product->name}")
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('batch_no')
                    ->label('Batch no.')
                    ->required(),

                DatePicker::make('received_date')
                    ->default(now())
                    ->required(),

                DatePicker::make('expiry_date')
                    ->after('received_date'),

                TextInput::make('supplier'),

                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}