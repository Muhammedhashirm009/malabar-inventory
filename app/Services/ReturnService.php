<?php

namespace App\Services;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReturnService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected LedgerService $ledgerService,
        protected SupplierLedgerService $supplierLedgerService
    ) {}

    /**
     * Create a purchase return with items wrapped in a database transaction.
     * Generates a return number, creates the return record and items,
     * decreases inventory stock for each returned item, and credit supplier ledger.
     */
    public function createPurchaseReturn(array $data, array $items): PurchaseReturn
    {
        return DB::transaction(function () use ($data, $items) {
            // Calculate total amount from items
            $totalAmount = collect($items)->sum(function (array $item) {
                return $item['quantity'] * $item['getting_rate'];
            });

            // Create purchase return record
            $purchaseReturn = PurchaseReturn::create([
                'return_number'  => $this->generatePurchaseReturnNumber(),
                'purchase_id'    => $data['purchase_id'] ?? null,
                'supplier_id'    => $data['supplier_id'] ?? null,
                'return_date'    => $data['return_date'] ?? Carbon::today()->toDateString(),
                'total_amount'   => $totalAmount,
                'reason'         => $data['reason'] ?? null,
            ]);

            // Create return items and decrease stock
            foreach ($items as $item) {
                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'product_id'         => $item['product_id'],
                    'quantity'           => $item['quantity'],
                    'getting_rate'       => $item['getting_rate'],
                    'total_price'        => $item['quantity'] * $item['getting_rate'],
                ]);

                $this->inventoryService->decreaseStock(
                    $item['product_id'],
                    $item['quantity']
                );
            }

            // Add credit entry to supplier ledger
            if ($purchaseReturn->supplier_id) {
                $this->supplierLedgerService->addEntry(
                    supplierId: $purchaseReturn->supplier_id,
                    type: 'credit',
                    amount: $totalAmount,
                    referenceType: 'purchase_return',
                    referenceId: $purchaseReturn->id,
                    description: "Purchase Return #{$purchaseReturn->return_number}",
                    date: $purchaseReturn->return_date
                );
            }

            return $purchaseReturn->load('items');
        });
    }

    /**
     * Delete a purchase return and reverse its effects.
     */
    public function deletePurchaseReturn(PurchaseReturn $purchaseReturn): void
    {
        DB::transaction(function () use ($purchaseReturn) {
            $supplierId = $purchaseReturn->supplier_id;

            // 1. Reverse inventory: increase stock back
            foreach ($purchaseReturn->items as $item) {
                $inventory = $this->inventoryService->getStock($item->product_id);
                $this->inventoryService->increaseStock(
                    productId: $item->product_id,
                    quantity: $item->quantity,
                    mrp: $inventory ? $inventory->mrp : 0,
                    gettingRate: $item->getting_rate,
                    saleRate: $inventory ? $inventory->sale_rate : 0
                );
            }

            // 2. Delete supplier ledger entry
            if ($supplierId) {
                $this->supplierLedgerService->deleteEntry('purchase_return', $purchaseReturn->id, $supplierId);
            }

            // 3. Delete items and the return itself
            $purchaseReturn->items()->delete();
            $purchaseReturn->delete();
        });
    }

    /**
     * Create a sale return with items wrapped in a database transaction.
     * Generates a return number, creates the return record and items,
     * increases inventory stock, and adds a credit entry to the customer ledger.
     */
    public function createSaleReturn(array $data, array $items): SaleReturn
    {
        return DB::transaction(function () use ($data, $items) {
            // Calculate total amount from items
            $totalAmount = collect($items)->sum(function (array $item) {
                return $item['quantity'] * $item['sale_rate'];
            });

            // Create sale return record
            $saleReturn = SaleReturn::create([
                'return_number' => $this->generateSaleReturnNumber(),
                'sale_id'       => $data['sale_id'] ?? null,
                'customer_id'   => $data['customer_id'],
                'return_date'   => $data['return_date'] ?? Carbon::today()->toDateString(),
                'total_amount'  => $totalAmount,
                'reason'        => $data['reason'] ?? null,
            ]);

            // Create return items and increase stock
            foreach ($items as $item) {
                // Get current inventory rates for restocking
                $inventory = $this->inventoryService->getStock($item['product_id']);

                SaleReturnItem::create([
                    'sale_return_id' => $saleReturn->id,
                    'product_id'     => $item['product_id'],
                    'quantity'       => $item['quantity'],
                    'sale_rate'      => $item['sale_rate'],
                    'total_price'    => $item['quantity'] * $item['sale_rate'],
                ]);

                $this->inventoryService->increaseStock(
                    $item['product_id'],
                    $item['quantity'],
                    $inventory ? $inventory->mrp : ($item['mrp'] ?? 0),
                    $inventory ? $inventory->getting_rate : ($item['getting_rate'] ?? 0),
                    $inventory ? $inventory->sale_rate : $item['sale_rate']
                );
            }

            // Add credit entry to customer ledger
            $this->ledgerService->addEntry(
                customerId: $data['customer_id'],
                type: 'credit',
                amount: $totalAmount,
                referenceType: 'sale_return',
                referenceId: $saleReturn->id,
                description: "Sale Return #{$saleReturn->return_number}",
                date: $saleReturn->return_date
            );

            return $saleReturn->load('items');
        });
    }

    /**
     * Delete a sale return and reverse its effects.
     */
    public function deleteSaleReturn(SaleReturn $saleReturn): void
    {
        DB::transaction(function () use ($saleReturn) {
            $customerId = $saleReturn->customer_id;

            // 1. Reverse inventory: decrease stock (we take back the restocked items)
            foreach ($saleReturn->items as $item) {
                $this->inventoryService->decreaseStock($item->product_id, $item->quantity);
            }

            // 2. Delete customer ledger entry
            if ($customerId) {
                $this->ledgerService->deleteEntry('sale_return', $saleReturn->id, $customerId);
            }

            // 3. Delete items and return record
            $saleReturn->items()->delete();
            $saleReturn->delete();
        });
    }

    /**
     * Generate a unique purchase return number in the format PR-YYYYMMDD-NNN.
     */
    private function generatePurchaseReturnNumber(): string
    {
        $today = Carbon::today()->format('Ymd');
        $prefix = "PR-{$today}-";

        $lastReturn = PurchaseReturn::where('return_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('return_number');

        if ($lastReturn) {
            $lastSequence = (int) substr($lastReturn, strlen($prefix));
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $prefix . str_pad($nextSequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a unique sale return number in the format SR-YYYYMMDD-NNN.
     */
    private function generateSaleReturnNumber(): string
    {
        $today = Carbon::today()->format('Ymd');
        $prefix = "SR-{$today}-";

        $lastReturn = SaleReturn::where('return_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('return_number');

        if ($lastReturn) {
            $lastSequence = (int) substr($lastReturn, strlen($prefix));
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $prefix . str_pad($nextSequence, 3, '0', STR_PAD_LEFT);
    }
}
