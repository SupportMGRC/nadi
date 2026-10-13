<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        // You can't change your own role or deactivate yourself (avoids locking yourself out)
        $isSelf = fn (?User $record): bool => $record !== null && $record->is(auth()->user());

        return $schema->components([
            Section::make('User')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Select::make('role')
                        ->options(User::ROLES)
                        ->default(User::ROLE_USER)
                        ->required()
                        ->helperText(fn (?User $record): ?string => $isSelf($record) ? 'You cannot change your own role.' : null)
                        ->disabled($isSelf),
                    Toggle::make('is_active')
                        ->label('Active (can log in)')
                        ->default(true)
                        ->inline(false)
                        ->disabled($isSelf),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->helperText(fn (string $operation): ?string => $operation === 'edit'
                            ? 'Leave blank to keep the current password.'
                            : 'At least 8 characters.'),
                ]),
        ]);
    }
}