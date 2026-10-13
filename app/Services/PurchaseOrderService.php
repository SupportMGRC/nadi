<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseOrderService
{
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