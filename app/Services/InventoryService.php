<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class InventoryService
{
    /**
     * Increase stock for a product. Creates the inventory record if it doesn't exist.
     * Updates MRP, getting rate, and sale rate with the latest supplied values.
     */
    public function increaseStock(
        int $productId,
        float $quantity,
        float $mrp,
        float $gettingRate,
        float $saleRate
    ): void {
        $inventory = Inventory::firstOrNew(['product_id' => $productId]);

        $oldQty = $inventory->quantity ?? 0;
        $newQty = $oldQty + $quantity;

        // Weighted average for getting_rate to maintain accurate cost basis
        if ($newQty > 0 && $oldQty > 0) {
            $inventory->getting_rate = round(
                (($oldQty * ($inventory->getting_rate ?? 0)) + ($quantity * $gettingRate)) / $newQty,
                2
            );
        } else {
            $inventory->getting_rate = $gettingRate;
        }

        $inventory->quantity = $newQty;
        $inventory->mrp = $mrp;
        $inventory->sale_rate = $saleRate;

        $inventory->save();
    }

    /**
     * Decrease stock for a product.
     *
     * @throws \RuntimeException If insufficient stock is available.
     */
    public function decreaseStock(int $productId, float $quantity): void
    {
        $inventory = Inventory::where('product_id', $productId)->first();

        if (!$inventory || $inventory->quantity < $quantity) {
            $available = $inventory ? $inventory->quantity : 0;
            throw new \RuntimeException(
                "Insufficient stock for product #{$productId}. Available: {$available}, Requested: {$quantity}"
            );
        }

        $inventory->quantity -= $quantity;
        $inventory->save();
    }

    /**
     * Get the inventory record for a product.
     */
    public function getStock(int $productId): ?Inventory
    {
        return Inventory::where('product_id', $productId)->first();
    }

    /**
     * Check whether sufficient stock is available for a product.
     */
    public function hasStock(int $productId, float $quantity): bool
    {
        $inventory = Inventory::where('product_id', $productId)->first();

        return $inventory && $inventory->quantity >= $quantity;
    }

    /**
     * Reverse an increase in stock (e.g. when deleting/editing a purchase).
     */
    public function reverseIncrease(int $productId, float $quantity): void
    {
        $inventory = Inventory::where('product_id', $productId)->first();
        if ($inventory) {
            $newQty = $inventory->quantity - $quantity;
            $inventory->quantity = max(0, $newQty);
            $inventory->save();
        }
    }

    /**
     * Reverse a decrease in stock (e.g. when deleting/editing a sale).
     */
    public function reverseDecrease(int $productId, float $quantity): void
    {
        $inventory = Inventory::where('product_id', $productId)->first();
        if ($inventory) {
            $inventory->quantity += $quantity;
            $inventory->save();
        }
    }
}
