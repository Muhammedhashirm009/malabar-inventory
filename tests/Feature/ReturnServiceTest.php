<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ReturnService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\InventoryService;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Inventory;
use App\Models\CustomerLedger;
use App\Models\SupplierLedger;
use App\Models\PurchaseReturn;
use App\Models\SaleReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReturnServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReturnService $returnService;
    protected PurchaseService $purchaseService;
    protected SaleService $saleService;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->returnService = app(ReturnService::class);
        $this->purchaseService = app(PurchaseService::class);
        $this->saleService = app(SaleService::class);
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

    private function createCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Test Customer',
            'phone' => '9876543210',
            'is_active' => true,
            'current_balance' => 0,
            'credit_limit' => 50000,
        ]);
    }

    private function createSupplier(): Supplier
    {
        return Supplier::create([
            'name' => 'Test Supplier',
            'phone' => '9876543210',
            'is_active' => true,
            'current_balance' => 0,
        ]);
    }

    // -----------------------------------------------------------------------
    // Purchase Returns
    // -----------------------------------------------------------------------

    public function test_purchase_return_decreases_inventory(): void
    {
        $product = $this->createProduct();
        $supplier = $this->createSupplier();

        // Purchase 50 units
        $purchase = $this->purchaseService->createPurchase(
            ['supplier_id' => $supplier->id, 'purchase_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 50, 'mrp' => 100, 'getting_rate' => 60, 'sale_rate' => 80]]
        );

        // Return 10 units
        $return = $this->returnService->createPurchaseReturn(
            ['purchase_id' => $purchase->id, 'supplier_id' => $supplier->id, 'return_date' => '2026-05-31', 'reason' => 'Defective'],
            [['product_id' => $product->id, 'quantity' => 10, 'getting_rate' => 60]]
        );

        // Inventory: 50 - 10 = 40
        $this->assertEquals(40, Inventory::where('product_id', $product->id)->value('quantity'));
        $this->assertEquals(600.00, $return->total_amount); // 10 * 60
        $this->assertCount(1, $return->items);
    }

    public function test_purchase_return_credits_supplier_ledger(): void
    {
        $product = $this->createProduct();
        $supplier = $this->createSupplier();

        $purchase = $this->purchaseService->createPurchase(
            ['supplier_id' => $supplier->id, 'purchase_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 50, 'mrp' => 100, 'getting_rate' => 60, 'sale_rate' => 80]]
        );

        // Supplier balance after purchase: 50 * 60 = 3000
        $supplier->refresh();
        $this->assertEquals(3000.00, $supplier->current_balance);

        $this->returnService->createPurchaseReturn(
            ['purchase_id' => $purchase->id, 'supplier_id' => $supplier->id, 'return_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 10, 'getting_rate' => 60]]
        );

        // Supplier balance after return: 3000 - 600 = 2400
        $supplier->refresh();
        $this->assertEquals(2400.00, $supplier->current_balance);

        // Should have credit entry in ledger
        $creditEntry = SupplierLedger::where('supplier_id', $supplier->id)
            ->where('type', 'credit')
            ->first();
        $this->assertNotNull($creditEntry);
        $this->assertEquals(600.00, $creditEntry->amount);
    }

    public function test_delete_purchase_return_reverses_effects(): void
    {
        $product = $this->createProduct();
        $supplier = $this->createSupplier();

        $purchase = $this->purchaseService->createPurchase(
            ['supplier_id' => $supplier->id, 'purchase_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 50, 'mrp' => 100, 'getting_rate' => 60, 'sale_rate' => 80]]
        );

        $return = $this->returnService->createPurchaseReturn(
            ['purchase_id' => $purchase->id, 'supplier_id' => $supplier->id, 'return_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 10, 'getting_rate' => 60]]
        );

        // Before delete: inventory = 40, supplier balance = 2400
        $this->assertEquals(40, Inventory::where('product_id', $product->id)->value('quantity'));

        $this->returnService->deletePurchaseReturn($return);

        // After delete: inventory restored to ~50, supplier balance back to 3000
        $inventory = Inventory::where('product_id', $product->id)->first();
        $this->assertEquals(50, $inventory->quantity);

        $this->assertNull(PurchaseReturn::find($return->id));

        $supplier->refresh();
        $this->assertEquals(3000.00, $supplier->current_balance);
    }

    // -----------------------------------------------------------------------
    // Sale Returns
    // -----------------------------------------------------------------------

    public function test_sale_return_increases_inventory(): void
    {
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->inventoryService->increaseStock($product->id, 50, 100, 60, 80);

        // Sell 20 units
        $sale = $this->saleService->createSale(
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 20, 'sale_rate' => 80.00]]
        );

        // Inventory should be 30
        $this->assertEquals(30, Inventory::where('product_id', $product->id)->value('quantity'));

        // Return 5 units
        $return = $this->returnService->createSaleReturn(
            ['sale_id' => $sale->id, 'customer_id' => $customer->id, 'return_date' => '2026-05-31', 'reason' => 'Wrong product'],
            [['product_id' => $product->id, 'quantity' => 5, 'sale_rate' => 80.00]]
        );

        // Inventory: 30 + 5 = 35
        $this->assertEquals(35, Inventory::where('product_id', $product->id)->value('quantity'));
        $this->assertEquals(400.00, $return->total_amount); // 5 * 80
    }

    public function test_sale_return_credits_customer_ledger(): void
    {
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->inventoryService->increaseStock($product->id, 50, 100, 60, 80);

        $sale = $this->saleService->createSale(
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 20, 'sale_rate' => 80.00]]
        );

        // Customer balance after sale: 20 * 80 = 1600
        $customer->refresh();
        $this->assertEquals(1600.00, $customer->current_balance);

        $this->returnService->createSaleReturn(
            ['sale_id' => $sale->id, 'customer_id' => $customer->id, 'return_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 5, 'sale_rate' => 80.00]]
        );

        // Customer balance after return: 1600 - 400 = 1200
        $customer->refresh();
        $this->assertEquals(1200.00, $customer->current_balance);

        $creditEntry = CustomerLedger::where('customer_id', $customer->id)
            ->where('type', 'credit')
            ->first();
        $this->assertNotNull($creditEntry);
        $this->assertEquals(400.00, $creditEntry->amount);
    }

    public function test_delete_sale_return_reverses_effects(): void
    {
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->inventoryService->increaseStock($product->id, 50, 100, 60, 80);

        $sale = $this->saleService->createSale(
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 20, 'sale_rate' => 80.00]]
        );

        $return = $this->returnService->createSaleReturn(
            ['sale_id' => $sale->id, 'customer_id' => $customer->id, 'return_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 5, 'sale_rate' => 80.00]]
        );

        // Before delete: inventory 35, customer balance 1200
        $this->assertEquals(35, Inventory::where('product_id', $product->id)->value('quantity'));

        $this->returnService->deleteSaleReturn($return);

        // After delete: inventory back to 30, customer balance back to 1600
        $this->assertEquals(30, Inventory::where('product_id', $product->id)->value('quantity'));

        $this->assertNull(SaleReturn::find($return->id));

        $customer->refresh();
        $this->assertEquals(1600.00, $customer->current_balance);
    }

    public function test_purchase_return_generates_return_number(): void
    {
        $product = $this->createProduct();
        $supplier = $this->createSupplier();

        $purchase = $this->purchaseService->createPurchase(
            ['supplier_id' => $supplier->id, 'purchase_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 50, 'mrp' => 100, 'getting_rate' => 60, 'sale_rate' => 80]]
        );

        $return = $this->returnService->createPurchaseReturn(
            ['purchase_id' => $purchase->id, 'supplier_id' => $supplier->id, 'return_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 5, 'getting_rate' => 60]]
        );

        $this->assertNotEmpty($return->return_number);
        $this->assertStringStartsWith('PR-', $return->return_number);
    }

    public function test_sale_return_generates_return_number(): void
    {
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->inventoryService->increaseStock($product->id, 50, 100, 60, 80);

        $sale = $this->saleService->createSale(
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 10, 'sale_rate' => 80.00]]
        );

        $return = $this->returnService->createSaleReturn(
            ['sale_id' => $sale->id, 'customer_id' => $customer->id, 'return_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 3, 'sale_rate' => 80.00]]
        );

        $this->assertNotEmpty($return->return_number);
        $this->assertStringStartsWith('SR-', $return->return_number);
    }
}
