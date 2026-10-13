<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\Actions\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrderActions::confirm()
                ->after(fn () => $this->redirect(OrderResource::getUrl('edit', ['record' => $this->record]))),
            OrderActions::cancel()
                ->after(fn () => $this->redirect(OrderResource::getUrl('edit', ['record' => $this->record]))),
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
        // No Save button once the order is confirmed or cancelled
        return $this->record->isDraft() ? parent::getFormActions() : [];
    }
}