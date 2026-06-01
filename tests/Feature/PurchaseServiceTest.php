<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\PurchaseService;
use App\Services\InventoryService;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Purchase;
use App\Models\Inventory;
use App\Models\SupplierLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PurchaseService $purchaseService;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->purchaseService = app(PurchaseService::class);
        $this->inventoryService = app(InventoryService::class);
    }

    private function createProduct(string $name = 'Test Product', string $sku = 'TP-001'): Product
    {
        return Product::create([
            'name' => $name,
            'sku' => $sku,
            'unit' => 'pcs',
            'mrp' => 100.00,
            'is_active' => true,
        ]);
    }

    private function createSupplier(string $name = 'Test Supplier'): Supplier
    {
        return Supplier::create([
            'name' => $name,
            'phone' => '9876543210',
            'is_active' => true,
            'current_balance' => 0,
        ]);
    }

    public function test_create_purchase_generates_invoice_number(): void
    {
        $product = $this->createProduct();
        $supplier = $this->createSupplier();

        $purchase = $this->purchaseService->createPurchase(
            ['supplier_id' => $supplier->id, 'purchase_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 10, 'mrp' => 100, 'getting_rate' => 60, 'sale_rate' => 80]]
        );

        $this->assertNotEmpty($purchase->invoice_number);
        $this->assertStringStartsWith('PUR-', $purchase->invoice_number);
    }

    public function test_create_purchase_increases_inventory(): void
    {
        $product = $this->createProduct();
        $supplier = $this->createSupplier();

        $purchase = $this->purchaseService->createPurchase(
            ['supplier_id' => $supplier->id, 'purchase_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 25, 'mrp' => 100, 'getting_rate' => 60, 'sale_rate' => 80]]
        );

        $inventory = Inventory::where('product_id', $product->id)->first();
        $this->assertNotNull($inventory);
        $this->assertEquals(25, $inventory->quantity);
        $this->assertEquals(100.00, $inventory->mrp);
        $this->assertEquals(60.00, $inventory->getting_rate);
        $this->assertEquals(80.00, $inventory->sale_rate);
    }

    public function test_create_purchase_adds_debit_to_supplier_ledger(): void
    {
        $product = $this->createProduct();
        $supplier = $this->createSupplier();

        $purchase = $this->purchaseService->createPurchase(
            ['supplier_id' => $supplier->id, 'purchase_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 10, 'mrp' => 100, 'getting_rate' => 60, 'sale_rate' => 80]]
        );

        // Total = 10 * 60 = 600
        $this->assertEquals(600.00, $purchase->total_amount);

        $ledger = SupplierLedger::where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($ledger);
        $this->assertEquals('debit', $ledger->type);
        $this->assertEquals(600.00, $ledger->amount);

        $supplier->refresh();
        $this->assertEquals(600.00, $supplier->current_balance);
    }

    public function test_create_purchase_calculates_total_from_items(): void
    {
        $product = $this->createProduct();
        $supplier = $this->createSupplier();

        $purchase = $this->purchaseService->createPurchase(
            ['supplier_id' => $supplier->id, 'purchase_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 10, 'mrp' => 100, 'getting_rate' => 60, 'sale_rate' => 80]]
        );

        // Total = quantity * getting_rate = 10 * 60 = 600
        $this->assertEquals(600.00, $purchase->total_amount);
        $this->assertCount(1, $purchase->items);
        $this->assertEquals(600.00, $purchase->items[0]->total_price);
    }

    public function test_create_purchase_with_multiple_items(): void
    {
        $product1 = $this->createProduct('Product A', 'PA-001');
        $product2 = $this->createProduct('Product B', 'PB-001');
        $supplier = $this->createSupplier();

        $purchase = $this->purchaseService->createPurchase(
            ['supplier_id' => $supplier->id, 'purchase_date' => '2026-05-31'],
            [
                ['product_id' => $product1->id, 'quantity' => 10, 'mrp' => 100, 'getting_rate' => 50, 'sale_rate' => 70],
                ['product_id' => $product2->id, 'quantity' => 5, 'mrp' => 200, 'getting_rate' => 120, 'sale_rate' => 160],
            ]
        );

        // Total: 10*50 + 5*120 = 500 + 600 = 1100
        $this->assertEquals(1100.00, $purchase->total_amount);
        $this->assertCount(2, $purchase->items);

        $this->assertEquals(10, Inventory::where('product_id', $product1->id)->value('quantity'));
        $this->assertEquals(5, Inventory::where('product_id', $product2->id)->value('quantity'));
    }

    public function test_update_purchase_reverses_and_reapplies_effects(): void
    {
        $product = $this->createProduct();
        $supplier = $this->createSupplier();

        $purchase = $this->purchaseService->createPurchase(
            ['supplier_id' => $supplier->id, 'purchase_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 20, 'mrp' => 100, 'getting_rate' => 60, 'sale_rate' => 80]]
        );

        $this->assertEquals(20, Inventory::where('product_id', $product->id)->value('quantity'));

        // Update to 10 units at different rate
        $updated = $this->purchaseService->updatePurchase(
            $purchase,
            ['supplier_id' => $supplier->id, 'purchase_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 10, 'mrp' => 110, 'getting_rate' => 65, 'sale_rate' => 85]]
        );

        // Inventory: reversed 20, added 10 → net 10
        $this->assertEquals(10, Inventory::where('product_id', $product->id)->value('quantity'));

        // Ledger: old deleted, new for 10*65=650
        $this->assertEquals(650.00, $updated->total_amount);
        $supplier->refresh();
        $this->assertEquals(650.00, $supplier->current_balance);
    }

    public function test_delete_purchase_restores_inventory_and_ledger(): void
    {
        $product = $this->createProduct();
        $supplier = $this->createSupplier();

        $purchase = $this->purchaseService->createPurchase(
            ['supplier_id' => $supplier->id, 'purchase_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 15, 'mrp' => 100, 'getting_rate' => 60, 'sale_rate' => 80]]
        );

        $this->purchaseService->deletePurchase($purchase);

        // Inventory reversed (back to 0 since this was the only purchase)
        $inventory = Inventory::where('product_id', $product->id)->first();
        $this->assertEquals(0, $inventory->quantity);

        // Purchase deleted
        $this->assertNull(Purchase::find($purchase->id));

        // Ledger entry deleted
        $this->assertEquals(0, SupplierLedger::where('supplier_id', $supplier->id)->count());

        $supplier->refresh();
        $this->assertEquals(0.00, $supplier->current_balance);
    }

    public function test_invoice_number_increments_per_day(): void
    {
        $product = $this->createProduct();
        $supplier = $this->createSupplier();

        $purchase1 = $this->purchaseService->createPurchase(
            ['supplier_id' => $supplier->id, 'purchase_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 5, 'mrp' => 100, 'getting_rate' => 60, 'sale_rate' => 80]]
        );

        $purchase2 = $this->purchaseService->createPurchase(
            ['supplier_id' => $supplier->id, 'purchase_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 5, 'mrp' => 100, 'getting_rate' => 60, 'sale_rate' => 80]]
        );

        $this->assertStringEndsWith('001', $purchase1->invoice_number);
        $this->assertStringEndsWith('002', $purchase2->invoice_number);
    }
}
