<?php

namespace App\Services\Imports;

use App\Models\Order;
use App\Models\Sku;
use App\Services\OrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Imports Shopify's "Orders → Export" CSV.
 * One row per line item; order-level fields are on the first row of each order.
 */
class ShopifyOrderImporter
{
    public const CHANNEL = 'shopify';

    protected const REQUIRED_COLUMNS = [
        'Name', 'Financial Status', 'Fulfillment Status', 'Created at', 'Total',
        'Lineitem quantity', 'Lineitem name', 'Lineitem price', 'Lineitem sku',
    ];

    protected array $result = [];

    public function __construct(protected OrderService $orders)
    {
    }

    /**
     * Returns a summary: created, updated, confirmed, cancelled, unchanged, errors[].
     * Throws InvalidArgumentException only if the file itself is unusable.
     */
    public function import(string $path): array
    {
        $this->result = [
            'created'   => 0,
            'updated'   => 0,
            'confirmed' => 0,
            'cancelled' => 0,
            'unchanged' => 0,
            'errors'    => [],
        ];

        foreach ($this->readOrders($path) as $orderNo => $rows) {
            try {
                $this->importOrder($orderNo, $rows);
            } catch (InvalidArgumentException $e) {
                $this->result['errors'][] = "{$orderNo}: {$e->getMessage()}";
            }
        }

        return $this->result;
    }

    /**
     * Read the CSV and group rows by Shopify order number (the "Name" column).
     */
    protected function readOrders(string $path): array
    {
        $handle = @fopen($path, 'r');
        if (! $handle) {
            throw new InvalidArgumentException('Cannot open the uploaded file.');
        }

        $header = fgetcsv($handle, null, ',', '"', '');
        if (! $header) {
            fclose($handle);
            throw new InvalidArgumentException('The file is empty.');
        }

        // Strip a possible UTF-8 BOM and stray spaces from the column names
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $header = array_map('trim', $header);

        $missing = array_diff(self::REQUIRED_COLUMNS, $header);
        if ($missing) {
            fclose($handle);
            throw new InvalidArgumentException(
                'This does not look like a Shopify orders export. Missing column(s): ' . implode(', ', $missing)
            );
        }

        $count = count($header);
        $orders = [];

        while (($cells = fgetcsv($handle, null, ',', '"', '')) !== false) {
            if ($cells === [null]) {
                continue; // blank line
            }

            $cells = array_pad(array_slice($cells, 0, $count), $count, '');
            $row = array_combine($header, $cells);

            $name = trim((string) $row['Name']);
            if ($name !== '') {
                $orders[$name][] = $row;
            }
        }

        fclose($handle);

        if (! $orders) {
            throw new InvalidArgumentException('No orders found in the file.');
        }

        return $orders;
    }

    protected function importOrder(string $orderNo, array $rows): void
    {
        $head = $rows[0];
        $target = $this->targetStatus($head);
        $lines = $this->lines($rows);   // throws if any SKU is missing/unknown — whole order skipped

        $order = Order::where('channel', self::CHANNEL)->where('external_ref', $orderNo)->first();

        // Already cancelled in NADI: final, leave it alone
        if ($order && $order->status === Order::STATUS_CANCELLED) {
            $this->result['unchanged']++;
            return;
        }

        // Already confirmed (stock taken): only a cancellation changes anything
        if ($order && $order->status === Order::STATUS_CONFIRMED) {
            $order->update($this->statusFields($head));

            if ($target === Order::STATUS_CANCELLED) {
                $this->orders->cancel($order, "Cancelled/refunded in Shopify ({$orderNo}).");
                $this->result['cancelled']++;
            } else {
                $this->result['unchanged']++;
            }
            return;
        }

        // New order, or still a draft: write header + items fresh from Shopify
        $isNew = $order === null;

        $order = DB::transaction(function () use ($order, $orderNo, $head, $lines) {
            $order ??= new Order([
                'channel'      => self::CHANNEL,
                'external_ref' => $orderNo,
                'created_by'   => auth()->id(),
            ]);

            $order->fill($this->headerFields($head));
            $order->save();

            $order->items()->delete();
            foreach ($lines as $line) {
                $order->items()->create($line);
            }

            return $order;
        });

        $this->result[$isNew ? 'created' : 'updated']++;

        if ($target === Order::STATUS_CONFIRMED) {
            try {
                $this->orders->confirm($order);
                $this->result['confirmed']++;
            } catch (InvalidArgumentException $e) {
                $this->result['errors'][] = "{$orderNo}: fulfilled in Shopify but kept as DRAFT — {$e->getMessage()}";
            }
        } elseif ($target === Order::STATUS_CANCELLED) {
            $this->orders->cancel($order, "Cancelled/refunded in Shopify ({$orderNo}).");
            $this->result['cancelled']++;
        }
    }

    /**
     * Shopify status → what NADI should do.
     */
    protected function targetStatus(array $head): string
    {
        $financial = strtolower($this->col($head, 'Financial Status'));
        $fulfillment = strtolower($this->col($head, 'Fulfillment Status'));

        if ($this->col($head, 'Cancelled at') !== '' || in_array($financial, ['refunded', 'voided'], true)) {
            return Order::STATUS_CANCELLED;
        }

        if ($fulfillment === 'fulfilled') {
            return Order::STATUS_CONFIRMED;
        }

        return Order::STATUS_DRAFT;
    }

    /**
     * Turn the CSV rows of one order into order_items data. All SKUs must exist in NADI.
     */
    protected function lines(array $rows): array
    {
        $lines = [];
        $problems = [];

        foreach ($rows as $row) {
            $qty = (int) $this->col($row, 'Lineitem quantity');
            if ($qty <= 0) {
                continue;
            }

            $code = $this->col($row, 'Lineitem sku');
            $name = $this->col($row, 'Lineitem name');

            if ($code === '') {
                $problems[] = "\"{$name}\" has no SKU in Shopify";
                continue;
            }

            $sku = Sku::where('sku_code', $code)->first();
            if (! $sku) {
                $problems[] = "SKU {$code} (\"{$name}\") not found in NADI";
                continue;
            }

            $lines[] = [
                'sku_id'     => $sku->id,
                'qty'        => $qty,
                'unit_price' => $this->money($row, 'Lineitem price'),
            ];
        }

        if ($problems) {
            throw new InvalidArgumentException('skipped — ' . implode('; ', $problems) . '.');
        }

        if (! $lines) {
            throw new InvalidArgumentException('skipped — no items.');
        }

        return $lines;
    }

    protected function headerFields(array $head): array
    {
        return [
            'order_date'       => $this->date($this->col($head, 'Created at')),
            'customer_name'    => $this->col($head, 'Shipping Name') ?: ($this->col($head, 'Billing Name') ?: 'Shopify customer'),
            'customer_phone'   => $this->col($head, 'Shipping Phone') ?: ($this->col($head, 'Billing Phone') ?: null),
            'customer_email'   => $this->col($head, 'Email') ?: null,
            'shipping_address' => $this->address($head),
            'shipping_fee'     => $this->money($head, 'Shipping'),
            'discount'         => $this->money($head, 'Discount Amount'),
            'total'            => $this->money($head, 'Total'),   // Shopify's own total (after discount, incl. shipping & tax)
            'notes'            => $this->col($head, 'Notes') ?: null,
        ] + $this->statusFields($head);
    }

    protected function statusFields(array $head): array
    {
        return [
            'payment_status'     => $this->col($head, 'Financial Status') ?: null,
            'fulfillment_status' => $this->col($head, 'Fulfillment Status') ?: null,
            'imported_at'        => now(),
        ];
    }

    protected function address(array $head): ?string
    {
        $zip = ltrim($this->col($head, 'Shipping Zip'), "'");   // Shopify adds ' to keep leading zeros
        $city = trim($zip . ' ' . $this->col($head, 'Shipping City'));

        $parts = [
            $this->col($head, 'Shipping Address1'),
            $this->col($head, 'Shipping Address2'),
            $city,
            $this->col($head, 'Shipping Province Name') ?: $this->col($head, 'Shipping Province'),
            $this->col($head, 'Shipping Country'),
        ];

        $parts = array_filter(array_map(fn ($p) => rtrim($p, ', '), $parts));

        return $parts ? implode("\n", $parts) : null;
    }

    protected function date(string $value): string
    {
        if ($value === '') {
            return today()->toDateString();
        }

        // e.g. "2026-10-08 13:14:04 +0800" → 2026-10-08 in Malaysia time
        return Carbon::parse($value)->setTimezone(config('app.timezone'))->toDateString();
    }

    protected function money(array $row, string $column): float
    {
        return (float) str_replace(',', '', $this->col($row, $column));
    }

    protected function col(array $row, string $column): string
    {
        return trim((string) ($row[$column] ?? ''));
    }
}