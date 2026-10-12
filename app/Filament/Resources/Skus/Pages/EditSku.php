<?php

namespace App\Filament\Resources\Skus\Pages;

use App\Filament\Resources\Skus\SkuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSku extends EditRecord
{
    protected static string $resource = SkuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
