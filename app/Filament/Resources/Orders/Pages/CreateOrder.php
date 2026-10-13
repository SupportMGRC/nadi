<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        // Items are saved by now — work out the total
        $this->record->recalculateTotal();
    }

    protected function getRedirectUrl(): string
    {
        // Go to the order page so you can Confirm it straight away
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}