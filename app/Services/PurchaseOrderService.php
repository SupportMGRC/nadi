<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseOrderService
{
    public function __construct(protected StockService $stock)
    {
    }

    /**
     * Draft → Submitted (sent for approval). Must have at least one item.
     */
    public function submit(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po = $this->lock($po);
            $this->expectStatus($po, [PurchaseOrder::STATUS_DRAFT], 'submit');

            if (! $po->items()->exists()) {
                throw new InvalidArgumentException("{$po->po_no} has no items.");
            }

            $po->recalculateTotal();
            $po->status = PurchaseOrder::STATUS_SUBMITTED;
            $po->submitted_at = now();
            $po->save();

            return $po;
        });
    }

    /**
     * Submitted → Draft (approver wants changes).
     */
    public function returnToDraft(PurchaseOrder $po, string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $reason) {
            $po = $this->lock($po);
            $this->expectStatus($po, [PurchaseOrder::STATUS_SUBMITTED], 'return to draft');

            $po->status = PurchaseOrder::STATUS_DRAFT;
            $po->submitted_at = null;
            $po->notes = $this->appendNote($po->notes, "Returned to draft: {$reason}");
            $po->save();

            return $po;
        });
    }

    /**
     * Submitted → Approved.
     */
    public function approve(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po = $this->lock($po);
            $this->expectStatus($po, [PurchaseOrder::STATUS_SUBMITTED], 'approve');

            $po->status = PurchaseOrder::STATUS_APPROVED;
            $po->approved_by = auth()->id();
            $po->approved_at = now();
            $po->save();

            return $po;
        });
    }

    /**
     * Approved → Ordered (PO actually sent to the supplier).
     */
    public function markOrdered(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po = $this->lock($po);
            $this->expectStatus($po, [PurchaseOrder::STATUS_APPROVED], 'mark as ordered');

            $po->status = PurchaseOrder::STATUS_ORDERED;
            $po->ordered_at = now();
            $po->save();

            return $po;
        });
    }

    /**
     * Goods arrived. Each line: purchase_order_item_id, qty, batch_no, expiry_date (optional).
     * Lines with qty 0 are skipped. All-or-nothing: one bad line rolls back everything.
     */
    public function receive(PurchaseOrder $po, string $receivedDate, array $lines): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $receivedDate, $lines) {
            $po = $this->lock($po);
            $this->expectStatus($po, [
                PurchaseOrder::STATUS_ORDERED,
                PurchaseOrder::STATUS_PARTIAL,
            ], 'receive goods for');

            $po->load('supplier');
            $totalReceived = 0;

            foreach ($lines as $line) {
                $qty = (int) ($line['qty'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $item = $po->items()
                    ->with('sku')
                    ->whereKey($line['purchase_order_item_id'] ?? 0)
                    ->lockForUpdate()
                    ->firstOrFail();

                $skuCode = $item->sku->sku_code;

                if ($qty > $item->qtyOutstanding()) {
                    throw new InvalidArgumentException(
                        "{$skuCode}: receiving {$qty} but only {$item->qtyOutstanding()} outstanding."
                    );
                }

                $batchNo = trim((string) ($line['batch_no'] ?? ''));
                if ($batchNo === '') {
                    throw new InvalidArgumentException("{$skuCode}: batch no. is required.");
                }

                $batch = Batch::where('sku_id', $item->sku_id)
                    ->where('batch_no', $batchNo)
                    ->first();

                if ($batch && (int) $batch->purchase_order_item_id !== (int) $item->id) {
                    throw new InvalidArgumentException(
                        "{$skuCode}: batch {$batchNo} already exists and is not from this PO line."
                    );
                }

                if (! $batch) {
                    $batch = Batch::create([
                        'sku_id'                 => $item->sku_id,
                        'purchase_order_item_id' => $item->id,
                        'batch_no'               => $batchNo,
                        'received_date'          => $receivedDate,
                        'expiry_date'            => $line['expiry_date'] ?: null,
                        'supplier'               => $po->supplier?->name,
                        'notes'                  => "Received against {$po->po_no}",
                    ]);
                }

                $this->stock->receive($batch, $qty, "Received against {$po->po_no}", $item);

                $item->qty_received = $item->qty_received + $qty;
                $item->save();

                $totalReceived += $qty;
            }

            if ($totalReceived === 0) {
                throw new InvalidArgumentException('Enter a quantity for at least one item.');
            }

            $allDone = $po->items()->whereColumn('qty_received', '<', 'qty_ordered')->doesntExist();

            $po->status = $allDone ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIAL;
            $po->save();

            return $po;
        });
    }

    /**
     * Cancel — only while nothing has been received yet.
     */
    public function cancel(PurchaseOrder $po, string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $reason) {
            $po = $this->lock($po);
            $this->expectStatus($po, [
                PurchaseOrder::STATUS_DRAFT,
                PurchaseOrder::STATUS_SUBMITTED,
                PurchaseOrder::STATUS_APPROVED,
                PurchaseOrder::STATUS_ORDERED,
            ], 'cancel');

            if ($po->items()->where('qty_received', '>', 0)->exists()) {
                throw new InvalidArgumentException("{$po->po_no} already has stock received and cannot be cancelled.");
            }

            $po->status = PurchaseOrder::STATUS_CANCELLED;
            $po->cancelled_at = now();
            $po->notes = $this->appendNote($po->notes, "Cancelled: {$reason}");
            $po->save();

            return $po;
        });
    }

    protected function lock(PurchaseOrder $po): PurchaseOrder
    {
        return PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
    }

    protected function expectStatus(PurchaseOrder $po, array $allowed, string $action): void
    {
        if (! in_array($po->status, $allowed, true)) {
            $status = PurchaseOrder::STATUSES[$po->status] ?? $po->status;
            throw new InvalidArgumentException("Cannot {$action} {$po->po_no} — it is {$status}.");
        }
    }

    protected function appendNote(?string $notes, string $line): string
    {
        return trim(($notes ? $notes . "\n" : '') . $line);
    }
}