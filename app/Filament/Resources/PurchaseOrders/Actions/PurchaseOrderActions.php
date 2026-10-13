<?php

namespace App\Filament\Resources\PurchaseOrders\Actions;

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use InvalidArgumentException;

class PurchaseOrderActions
{
    public static function submit(): Action
    {
        return Action::make('submitPo')
            ->label('Submit')
            ->icon('heroicon-o-paper-airplane')
            ->color('warning')
            ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrder::STATUS_DRAFT)
            ->requiresConfirmation()
            ->modalHeading(fn (PurchaseOrder $record): string => "Submit {$record->po_no} for approval?")
            ->modalDescription('The PO will be locked from editing.')
            ->modalSubmitActionLabel('Yes, submit')
            ->action(fn (PurchaseOrder $record) => self::run(
                fn () => app(PurchaseOrderService::class)->submit($record),
                "{$record->po_no} submitted for approval",
            ));
    }

    public static function approve(): Action
    {
        return Action::make('approvePo')
            ->label('Approve')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrder::STATUS_SUBMITTED)
            ->requiresConfirmation()
            ->modalHeading(fn (PurchaseOrder $record): string => "Approve {$record->po_no}?")
            ->modalSubmitActionLabel('Yes, approve')
            ->action(fn (PurchaseOrder $record) => self::run(
                fn () => app(PurchaseOrderService::class)->approve($record),
                "{$record->po_no} approved",
            ));
    }

    public static function returnToDraft(): Action
    {
        return Action::make('returnPoToDraft')
            ->label('Return to draft')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrder::STATUS_SUBMITTED)
            ->schema([
                Textarea::make('reason')
                    ->label('What needs to change?')
                    ->required()
                    ->rows(2),
            ])
            ->modalSubmitActionLabel('Return to draft')
            ->action(fn (PurchaseOrder $record, array $data) => self::run(
                fn () => app(PurchaseOrderService::class)->returnToDraft($record, $data['reason']),
                "{$record->po_no} returned to draft",
            ));
    }

    public static function markOrdered(): Action
    {
        return Action::make('markPoOrdered')
            ->label('Mark as ordered')
            ->icon('heroicon-o-truck')
            ->color('primary')
            ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrder::STATUS_APPROVED)
            ->requiresConfirmation()
            ->modalHeading(fn (PurchaseOrder $record): string => "Mark {$record->po_no} as ordered?")
            ->modalDescription('Use this once the PO has actually been sent to the supplier.')
            ->modalSubmitActionLabel('Yes, ordered')
            ->action(fn (PurchaseOrder $record) => self::run(
                fn () => app(PurchaseOrderService::class)->markOrdered($record),
                "{$record->po_no} marked as ordered",
            ));
    }

    public static function cancel(): Action
    {
        return Action::make('cancelPo')
            ->label('Cancel PO')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, [
                PurchaseOrder::STATUS_DRAFT,
                PurchaseOrder::STATUS_SUBMITTED,
                PurchaseOrder::STATUS_APPROVED,
                PurchaseOrder::STATUS_ORDERED,
            ], true))
            ->requiresConfirmation()
            ->modalHeading(fn (PurchaseOrder $record): string => "Cancel {$record->po_no}?")
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(2),
            ])
            ->modalSubmitActionLabel('Yes, cancel PO')
            ->action(fn (PurchaseOrder $record, array $data) => self::run(
                fn () => app(PurchaseOrderService::class)->cancel($record, $data['reason']),
                "{$record->po_no} cancelled",
            ));
    }

    /**
     * Run a service call and show a green or red notification.
     */
    protected static function run(callable $callback, string $successTitle): void
    {
        try {
            $callback();
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Action failed')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title($successTitle)
            ->success()
            ->send();
    }
}