<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Models\Order;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use InvalidArgumentException;

class OrderActions
{
    public static function confirm(): Action
    {
        return Action::make('confirmOrder')
            ->label('Confirm')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Order $record): bool => $record->isDraft())
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record): string => "Confirm {$record->order_no}?")
            ->modalDescription('Stock will be picked from the oldest batches and deducted. The order can no longer be edited after this.')
            ->modalSubmitActionLabel('Yes, confirm')
            ->action(function (Order $record): void {
                try {
                    app(OrderService::class)->confirm($record);
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Order not confirmed')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title("{$record->order_no} confirmed")
                    ->body('Stock has been deducted.')
                    ->success()
                    ->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancelOrder')
            ->label('Cancel order')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Order $record): bool => $record->status !== Order::STATUS_CANCELLED)
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record): string => "Cancel {$record->order_no}?")
            ->modalDescription(fn (Order $record): string => $record->isDraft()
                ? 'This draft will be marked as cancelled. No stock is affected.'
                : 'All picked stock will be returned to the same batches.')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(2),
            ])
            ->modalSubmitActionLabel('Yes, cancel order')
            ->action(function (Order $record, array $data): void {
                try {
                    app(OrderService::class)->cancel($record, $data['reason']);
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Order not cancelled')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title("{$record->order_no} cancelled")
                    ->success()
                    ->send();
            });
    }
}