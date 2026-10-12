<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug($state ?? ''))),

                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true),

                TextInput::make('category')
                    ->placeholder('e.g. Supplement, Serum'),

                Select::make('type')
                    ->options([
                        'single'    => 'Single product',
                        'bundle'    => 'Bundle',
                        'packaging' => 'Packaging (non-sellable)',
                    ])
                    ->default('single')
                    ->required(),

                Toggle::make('is_sellable')
                    ->label('Sellable')
                    ->default(true),

                Select::make('status')
                    ->options([
                        'active'   => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }
}