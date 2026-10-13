<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sku;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderService
{
    public function __construct(protected StockService $stock)
    {
    }

    /**
     * Draft → Confirmed: explode bundles, pick batches FIFO, deduct stock.
     * All-or-nothing: if any SKU is short, nothing is deducted.
     */
    public function confirm(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $order->isDraft()) {
                throw new InvalidArgumentException("Order {$order->order_no} is not a draft (status: {$order->status}).");
            }

            $order->load('items.sku.product', 'items.sku.components');

            if ($order->items->isEmpty()) {
                throw new InvalidArgumentException("Order {$order->order_no} has no items.");
            }

            foreach ($order->items as $item) {
                foreach ($this->explode($item) as $skuId => $qty) {
                    $this->pick($order, $item, $skuId, $qty);
                }
            }

            $order->status = Order::STATUS_CONFIRMED;
            $order->confirmed_at = now();
            $order->save();

            return $order;
        });
    }

    /**
     * Confirmed → Cancelled: return every picked qty to its original batch.
     * Draft → Cancelled: just mark it (no stock was touched).
     */
    public function cancel(Order $order, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === Order::STATUS_CANCELLED) {
                throw new InvalidArgumentException("Order {$order->order_no} is already cancelled.");
            }

            if ($order->status === Order::STATUS_CONFIRMED) {
                foreach ($order->picks()->with('batch')->get() as $pick) {
                    $this->stock->reverseIssue(
                        $pick->batch,
                        $pick->qty,
                        $pick,
                        trim("Cancel order {$order->order_no}. {$reason}")
                    );
                }
            }

            $order->status = Order::STATUS_CANCELLED;
            $order->cancelled_at = now();
            if ($reason) {
                $order->notes = trim(($order->notes ? $order->notes . "\n" : '') . "Cancelled: {$reason}");
            }
            $order->save();

            return $order;
        });
    }

    /**
     * Turn one order line into physical SKUs: [sku_id => qty].
     * Single/packaging SKU → itself. Bundle → its components × line qty.
     */
    protected function explode(OrderItem $item): array
    {
        $sku = $item->sku;

        if ($sku->product?->type !== 'bundle') {
            return [$sku->id => (int) $item->qty];
        }

        if ($sku->components->isEmpty()) {
            throw new InvalidArgumentException("Bundle {$sku->sku_code} has no components set up.");
        }

        $needs = [];
        foreach ($sku->components as $component) {
            $id = $component->component_sku_id;
            $needs[$id] = ($needs[$id] ?? 0) + ((int) $component->qty * (int) $item->qty);
        }

        return $needs;
    }

    /**
     * Take $needed units of one SKU from its batches, oldest received first.
     * Expired batches are skipped.
     */
    protected function pick(Order $order, OrderItem $item, int $skuId, int $needed): void
    {
        $sku = Sku::findOrFail($skuId);

        $batches = Batch::where('sku_id', $skuId)
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                  ->orWhereDate('expiry_date', '>=', today());
            })
            ->orderBy('received_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $needed;

        foreach ($batches as $batch) {
            if ($remaining === 0) {
                break;
            }

            $available = $this->stock->onHand($batch);
            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);

            $pick = $item->picks()->create([
                'sku_id'   => $skuId,
                'batch_id' => $batch->id,
                'qty'      => $take,
            ]);

            $this->stock->issue($batch, $take, $pick, "Order {$order->order_no}");

            $remaining -= $take;
        }

        if ($remaining > 0) {
            $found = $needed - $remaining;
            throw new InvalidArgumentException(
                "Not enough stock for {$sku->sku_code}: need {$needed}, only {$found} available in unexpired batches."
            );
        }
    }
}