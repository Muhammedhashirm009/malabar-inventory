<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchaseService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected SupplierLedgerService $supplierLedgerService
    ) {}

    /**
     * Create a purchase with items wrapped in a database transaction.
     * Generates an invoice number, creates the purchase record and items,
     * increases inventory stock for each item, and adds a debit entry to supplier ledger.
     */
    public function createPurchase(array $data, array $items): Purchase
    {
        return DB::transaction(function () use ($data, $items) {
            // Calculate total amount from items
            $totalAmount = collect($items)->sum(function (array $item) {
                return $item['quantity'] * $item['getting_rate'];
            });

            // Create purchase record
            $purchase = Purchase::create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'supplier_id'    => $data['supplier_id'] ?? null,
                'purchase_date'  => $data['purchase_date'] ?? Carbon::today()->toDateString(),
                'total_amount'   => $totalAmount,
                'notes'          => $data['notes'] ?? null,
            ]);

            // Create purchase items and increase stock
            foreach ($items as $item) {
                PurchaseItem::create([
                    'purchase_id'  => $purchase->id,
                    'product_id'   => $item['product_id'],
                    'quantity'     => $item['quantity'],
                    'mrp'          => $item['mrp'],
                    'getting_rate' => $item['getting_rate'],
                    'sale_rate'    => $item['sale_rate'],
                    'total_price'  => $item['quantity'] * $item['getting_rate'],
                ]);

                $this->inventoryService->increaseStock(
                    $item['product_id'],
                    $item['quantity'],
                    $item['mrp'],
                    $item['getting_rate'],
                    $item['sale_rate']
                );
            }

            // Add debit entry to supplier ledger
            if ($purchase->supplier_id) {
                $this->supplierLedgerService->addEntry(
                    supplierId: $purchase->supplier_id,
                    type: 'debit',
                    amount: $totalAmount,
                    referenceType: 'purchase',
                    referenceId: $purchase->id,
                    description: "Purchase Invoice #{$purchase->invoice_number}",
                    date: $purchase->purchase_date
                );
            }

            return $purchase->load('items');
        });
    }

    /**
     * Update a purchase with items wrapped in a database transaction.
     */
    public function updatePurchase(Purchase $purchase, array $data, array $items): Purchase
    {
        return DB::transaction(function () use ($purchase, $data, $items) {
            $oldSupplierId = $purchase->supplier_id;

            // 1. Reverse old inventory effects
            foreach ($purchase->items as $oldItem) {
                $this->inventoryService->reverseIncrease($oldItem->product_id, $oldItem->quantity);
            }

            // 2. Delete old ledger entry
            if ($oldSupplierId) {
                $this->supplierLedgerService->deleteEntry('purchase', $purchase->id, $oldSupplierId);
            }

            // 3. Delete old items
            $purchase->items()->delete();

            // 4. Calculate new total amount
            $totalAmount = collect($items)->sum(function (array $item) {
                return $item['quantity'] * $item['getting_rate'];
            });

            // 5. Update purchase header
            $purchase->update([
                'supplier_id'   => $data['supplier_id'] ?? null,
                'purchase_date' => $data['purchase_date'] ?? Carbon::today()->toDateString(),
                'total_amount'  => $totalAmount,
                'notes'         => $data['notes'] ?? null,
            ]);

            // 6. Create new items and increase stock
            foreach ($items as $item) {
                PurchaseItem::create([
                    'purchase_id'  => $purchase->id,
                    'product_id'   => $item['product_id'],
                    'quantity'     => $item['quantity'],
                    'mrp'          => $item['mrp'],
                    'getting_rate' => $item['getting_rate'],
                    'sale_rate'    => $item['sale_rate'],
                    'total_price'  => $item['quantity'] * $item['getting_rate'],
                ]);

                $this->inventoryService->increaseStock(
                    $item['product_id'],
                    $item['quantity'],
                    $item['mrp'],
                    $item['getting_rate'],
                    $item['sale_rate']
                );
            }

            // 7. Add new ledger entry
            if ($purchase->supplier_id) {
                $this->supplierLedgerService->addEntry(
                    supplierId: $purchase->supplier_id,
                    type: 'debit',
                    amount: $totalAmount,
                    referenceType: 'purchase',
                    referenceId: $purchase->id,
                    description: "Purchase Invoice #{$purchase->invoice_number}",
                    date: $purchase->purchase_date
                );
            }



            return $purchase->load('items');
        });
    }

    /**
     * Delete a purchase and reverse its inventory and ledger effects.
     */
    public function deletePurchase(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $supplierId = $purchase->supplier_id;

            // 1. Reverse inventory effects
            foreach ($purchase->items as $item) {
                $this->inventoryService->reverseIncrease($item->product_id, $item->quantity);
            }

            // 2. Delete ledger entry
            if ($supplierId) {
                $this->supplierLedgerService->deleteEntry('purchase', $purchase->id, $supplierId);
            }

            // 3. Delete purchase items and purchase record
            $purchase->items()->delete();
            $purchase->delete();
        });
    }

    /**
     * Generate a unique invoice number in the format PUR-YYYYMMDD-NNN.
     * Increments the sequence per day.
     */
    public function generateInvoiceNumber(): string
    {
        $prefix = config('settings.purchase_invoice_prefix', 'PUR');
        $suffix = config('settings.purchase_invoice_suffix', '');

        $today = Carbon::today()->format('Ymd');
        $searchPrefix = "{$prefix}-{$today}-";

        $lastInvoice = Purchase::where('invoice_number', 'like', "{$searchPrefix}%")
            ->orderByDesc('id')
            ->value('invoice_number');

        if ($lastInvoice) {
            $seqPart = substr($lastInvoice, strlen($searchPrefix));
            if ($suffix !== '') {
                if (str_ends_with($seqPart, $suffix)) {
                    $seqPart = substr($seqPart, 0, -strlen($suffix));
                }
            }
            $lastSequence = (int) $seqPart;
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $searchPrefix . str_pad($nextSequence, 3, '0', STR_PAD_LEFT) . $suffix;
    }
}
