<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SaleService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected LedgerService $ledgerService
    ) {}

    /**
     * Create a sale with items wrapped in a database transaction.
     * Generates an invoice number, creates the sale record and items,
     * decreases inventory stock, and adds a debit entry to the customer ledger.
     */
    public function createSale(array $data, array $items): Sale
    {
        return DB::transaction(function () use ($data, $items) {
            // Calculate total amount from items
            $totalAmount = collect($items)->sum(function (array $item) {
                $discount = $item['discount'] ?? 0;
                return $item['quantity'] * ($item['sale_rate'] - $discount);
            });

            // Create sale record
            $sale = Sale::create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'customer_id'    => $data['customer_id'],
                'sale_date'      => $data['sale_date'] ?? Carbon::today()->toDateString(),
                'total_amount'   => $totalAmount,
                'notes'          => $data['notes'] ?? null,
            ]);

            // Create sale items, capture getting_rate for profit calculation, decrease stock
            foreach ($items as $item) {
                // Get current getting_rate from inventory for profit tracking
                $inventory = $this->inventoryService->getStock($item['product_id']);
                $gettingRate = $inventory ? (float) $inventory->getting_rate : 0;

                // Validate that sale_rate is greater than getting_rate
                $netRate = $item['sale_rate'] - ($item['discount'] ?? 0);
                if ($netRate < $gettingRate) {
                    $productName = $inventory && $inventory->product ? $inventory->product->name : ('Product #' . $item['product_id']);
                    throw new \RuntimeException("Cannot sell '{$productName}' below getting rate (₹" . number_format($gettingRate, 2) . ")!");
                }

                SaleItem::create([
                    'sale_id'      => $sale->id,
                    'product_id'   => $item['product_id'],
                    'quantity'     => $item['quantity'],
                    'mrp'          => $item['mrp'] ?? ($inventory ? $inventory->mrp : 0),
                    'getting_rate' => $gettingRate,
                    'sale_rate'    => $item['sale_rate'],
                    'discount'     => $item['discount'] ?? 0,
                    'total_price'  => $item['quantity'] * ($item['sale_rate'] - ($item['discount'] ?? 0)),
                ]);

                $this->inventoryService->decreaseStock(
                    $item['product_id'],
                    $item['quantity']
                );
            }

            // Add debit entry to customer ledger
            $this->ledgerService->addEntry(
                customerId: $data['customer_id'],
                type: 'debit',
                amount: $totalAmount,
                referenceType: 'sale',
                referenceId: $sale->id,
                description: "Sale #{$sale->invoice_number}",
                date: $sale->sale_date
            );

            return $sale->load('items');
        });
    }

    /**
     * Update a sale with items wrapped in a database transaction.
     */
    public function updateSale(Sale $sale, array $data, array $items): Sale
    {
        return DB::transaction(function () use ($sale, $data, $items) {
            $oldCustomerId = $sale->customer_id;

            // 1. Reverse old inventory effects (increase stock back)
            foreach ($sale->items as $oldItem) {
                $this->inventoryService->reverseDecrease($oldItem->product_id, $oldItem->quantity);
            }

            // 2. Delete old customer ledger entry
            if ($oldCustomerId) {
                $this->ledgerService->deleteEntry('sale', $sale->id, $oldCustomerId);
            }

            // 3. Delete old items
            $sale->items()->delete();

            // 4. Calculate new total amount
            $totalAmount = collect($items)->sum(function (array $item) {
                $discount = $item['discount'] ?? 0;
                return $item['quantity'] * ($item['sale_rate'] - $discount);
            });

            // 5. Update sale record
            $sale->update([
                'customer_id'  => $data['customer_id'],
                'sale_date'    => $data['sale_date'] ?? Carbon::today()->toDateString(),
                'total_amount' => $totalAmount,
                'notes'        => $data['notes'] ?? null,
            ]);

            // 6. Create new sale items and decrease stock
            foreach ($items as $item) {
                $inventory = $this->inventoryService->getStock($item['product_id']);
                $gettingRate = $inventory ? (float) $inventory->getting_rate : 0;

                // Validate that sale_rate is greater than getting_rate
                $netRate = $item['sale_rate'] - ($item['discount'] ?? 0);
                if ($netRate < $gettingRate) {
                    $productName = $inventory && $inventory->product ? $inventory->product->name : ('Product #' . $item['product_id']);
                    throw new \RuntimeException("Cannot sell '{$productName}' below getting rate (₹" . number_format($gettingRate, 2) . ")!");
                }

                SaleItem::create([
                    'sale_id'      => $sale->id,
                    'product_id'   => $item['product_id'],
                    'quantity'     => $item['quantity'],
                    'mrp'          => $item['mrp'] ?? ($inventory ? $inventory->mrp : 0),
                    'getting_rate' => $gettingRate,
                    'sale_rate'    => $item['sale_rate'],
                    'discount'     => $item['discount'] ?? 0,
                    'total_price'  => $item['quantity'] * ($item['sale_rate'] - ($item['discount'] ?? 0)),
                ]);

                $this->inventoryService->decreaseStock(
                    $item['product_id'],
                    $item['quantity']
                );
            }

            // 7. Add new debit entry to customer ledger
            $this->ledgerService->addEntry(
                customerId: $sale->customer_id,
                type: 'debit',
                amount: $totalAmount,
                referenceType: 'sale',
                referenceId: $sale->id,
                description: "Sale #{$sale->invoice_number}",
                date: $sale->sale_date
            );



            return $sale->load('items');
        });
    }

    /**
     * Delete a sale and reverse inventory/ledger effects.
     */
    public function deleteSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $customerId = $sale->customer_id;

            // 1. Reverse inventory effects (increase stock back)
            foreach ($sale->items as $item) {
                $this->inventoryService->reverseDecrease($item->product_id, $item->quantity);
            }

            // 2. Delete customer ledger entry
            if ($customerId) {
                $this->ledgerService->deleteEntry('sale', $sale->id, $customerId);
            }

            // 3. Delete sale items and sale record
            $sale->items()->delete();
            $sale->delete();
        });
    }

    /**
     * Generate a unique invoice number in the format SAL-YYYYMMDD-NNN.
     * Increments the sequence per day.
     */
    public function generateInvoiceNumber(): string
    {
        $prefix = config('settings.sale_invoice_prefix', 'SAL');
        $suffix = config('settings.sale_invoice_suffix', '');

        $today = Carbon::today()->format('Ymd');
        $searchPrefix = "{$prefix}-{$today}-";

        $lastInvoice = Sale::where('invoice_number', 'like', "{$searchPrefix}%")
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
