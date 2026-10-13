<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\Actions\PurchaseOrderActions;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        $reload = fn () => $this->redirect(PurchaseOrderResource::getUrl('edit', ['record' => $this->record]));

        return [
            PurchaseOrderActions::submit()
                // Save whatever is on screen first, so you can't submit stale data
                ->before(fn () => $this->save(shouldRedirect: false, shouldSendSavedNotification: false))
                ->after($reload),
            PurchaseOrderActions::approve()->after($reload),
            PurchaseOrderActions::returnToDraft()->after($reload),
            PurchaseOrderActions::markOrdered()->after($reload),
            PurchaseOrderActions::receive()->after($reload),
            PurchaseOrderActions::cancel()->after($reload),
            DeleteAction::make()
                ->visible(fn (): bool => $this->record->isDraft()),
        ];
    }

    protected function afterSave(): void
    {
        $this->record->recalculateTotal();
    }

    protected function getFormActions(): array
    {
        return $this->record->isDraft() ? parent::getFormActions() : [];
    }
}