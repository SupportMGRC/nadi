<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    /** Same rule as the red expiry highlight on the Batches screen. */
    public const EXPIRY_WARNING_DAYS = 60;

    /** On-hand of one SKU = sum of all its stock movements. */
    protected const SKU_ON_HAND_SQL = '(select coalesce(sum(qty), 0) from stock_movements where stock_movements.sku_id = skus.id)';

    /** On-hand of one batch = sum of all its stock movements. */
    protected const BATCH_ON_HAND_SQL = '(select coalesce(sum(qty), 0) from stock_movements where stock_movements.batch_id = batches.id)';

    /**
     * SKUs at or below reorder level. Bundles are skipped (they hold no stock themselves).
     */
    public function lowStockSkus(): Builder
    {
        return Sku::query()
            ->select('skus.*')
            ->selectRaw(self::SKU_ON_HAND_SQL . ' as on_hand')
            ->whereHas('product', fn (Builder $q) => $q->where('type', '!=', 'bundle'))
            ->whereRaw(self::SKU_ON_HAND_SQL . ' <= coalesce(skus.reorder_level, 0)');
    }

    /**
     * Batches that still have stock and expire within 60 days (or already expired).
     */
    public function nearExpiryBatches(): Builder
    {
        return Batch::query()
            ->select('batches.*')
            ->selectRaw(self::BATCH_ON_HAND_SQL . ' as on_hand')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays(self::EXPIRY_WARNING_DAYS))
            ->whereRaw(self::BATCH_ON_HAND_SQL . ' > 0')
            ->orderBy('expiry_date');
    }

    /**
     * POs that are with procurement / supplier: not draft, not finished, not cancelled.
     */
    public function openPurchaseOrders(): Builder
    {
        return PurchaseOrder::query()
            ->whereNotIn('status', ['draft', 'received', 'cancelled']);
    }

    /**
     * Orders created today (Malaysia time), excluding cancelled.
     */
    public function todaysOrders(): Builder
    {
        return Order::query()
            ->whereDate('created_at', today())
            ->where('status', '!=', 'cancelled');
    }
}