<?php

namespace App\Filament\Resources\Skus\Pages;

use App\Filament\Resources\Skus\SkuResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSku extends CreateRecord
{
    protected static string $resource = SkuResource::class;
}
