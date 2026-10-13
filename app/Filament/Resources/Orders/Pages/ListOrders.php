<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\Imports\ShopifyOrderImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importShopify')
                ->label('Import Shopify CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Import Shopify orders')
                ->modalDescription('In Shopify: Orders → Export → CSV. Uploading the same orders again is safe — they are updated, never duplicated.')
                ->schema([
                    FileUpload::make('file')
                        ->label('Orders export (.csv)')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/vnd.ms-excel',
                            'text/x-comma-separated-values',
                        ])
                        ->maxSize(10240)
                        ->required(),
                ])
                ->modalSubmitActionLabel('Import')
                ->action(function (array $data): void {
                    $path = Storage::disk('local')->path($data['file']);

                    try {
                        $r = app(ShopifyOrderImporter::class)->import($path);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Import failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    } finally {
                        // The file contains customer data — don't keep it
                        Storage::disk('local')->delete($data['file']);
                    }

                    $body = e("New: {$r['created']} · Drafts updated: {$r['updated']} · Confirmed: {$r['confirmed']} · Cancelled: {$r['cancelled']} · Unchanged: {$r['unchanged']}");

                    if ($r['errors']) {
                        $body .= '<br><br><strong>Needs attention (' . count($r['errors']) . '):</strong><br>'
                            . collect($r['errors'])->take(15)->map(fn ($m) => '• ' . e($m))->join('<br>');
                    }

                    $notification = Notification::make()
                        ->title('Shopify import finished')
                        ->body(new HtmlString($body))
                        ->persistent();

                    $r['errors'] ? $notification->warning() : $notification->success();
                    $notification->send();
                }),

            CreateAction::make()
                ->label('New manual order'),
        ];
    }
}