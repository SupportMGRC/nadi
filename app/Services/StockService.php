<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Location;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockService
{
    /**
     * Stock arriving from the supplier.
     */
    public function receive(Batch $batch, int $qty, ?string $notes = null, ?Model $reference = null): StockMovement
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Received quantity must be more than 0.');
        }

        return $this->record($batch, $qty, 'receive', $notes, $reference);
    }

    /**
     * Manual correction: + to add, − to remove (recount, damage, etc).
     */
    public function adjust(Batch $batch, int $qty, string $reason): StockMovement
    {
        if ($qty === 0) {
            throw new InvalidArgumentException('Adjustment cannot be 0.');
        }

        return $this->record($batch, $qty, 'adjustment', $reason);
    }

    /**
     * Stock leaving for an order. Pass a positive qty; it is stored as negative.
     */
    public function issue(Batch $batch, int $qty, ?Model $reference = null, ?string $notes = null): StockMovement
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Issue quantity must be more than 0.');
        }

        return $this->record($batch, -$qty, 'sale', $notes, $reference);
    }

    /**
     * Put back stock from a cancelled order. Pass a positive qty.
     */
    public function reverseIssue(Batch $batch, int $qty, ?Model $reference = null, ?string $notes = null): StockMovement
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Reversal quantity must be more than 0.');
        }

        return $this->record($batch, $qty, 'order_cancel', $notes, $reference);
    }

    /**
     * Current stock of one batch = sum of all its movements.
     */
    public function onHand(Batch $batch): int
    {
        return (int) $batch->movements()->sum('qty');
    }

    protected function record(Batch $batch, int $qty, string $type, ?string $notes, ?Model $reference = null): StockMovement
    {
        return DB::transaction(function () use ($batch, $qty, $type, $notes, $reference) {
            // Lock this batch row so two people can't remove the same stock at the same moment.
            $batch = Batch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($qty < 0) {
                $onHand = $this->onHand($batch);

                if ($onHand + $qty < 0) {
                    throw new InvalidArgumentException("Not enough stock in batch {$batch->batch_no}. On hand: {$onHand}.");
                }
            }

            return StockMovement::create([
                'sku_id'         => $batch->sku_id,
                'batch_id'       => $batch->id,
                'location_id'    => $this->defaultLocation()->id,
                'qty'            => $qty,
                'type'           => $type,
                'notes'          => $notes,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id'   => $reference?->getKey(),
                'created_by'     => auth()->id(),
            ]);
        });
    }

    protected function defaultLocation(): Location
    {
        return Location::where('code', 'MGRC')->firstOrFail();
    }
}