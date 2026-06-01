<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\SaleService;
use App\Services\InventoryService;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Inventory;
use App\Models\CustomerLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SaleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SaleService $saleService;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function createCustomer(string $name = 'Test Customer'): Customer
    {
        return Customer::create([
            'name' => $name,
            'phone' => '9876543210',
            'is_active' => true,
            'current_balance' => 0,
            'credit_limit' => 50000,
        ]);
    }

    private function stockProduct(int $productId, float $qty = 100, float $mrp = 100, float $gettingRate = 60, float $saleRate = 80): void
    {
        $this->inventoryService->increaseStock($productId, $qty, $mrp, $gettingRate, $saleRate);
    }

    public function test_create_sale_generates_invoice_number(): void
    {
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->stockProduct($product->id);

        $sale = $this->saleService->createSale(
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 2, 'sale_rate' => 80.00, 'mrp' => 100.00]]
        );

        $this->assertNotEmpty($sale->invoice_number);
        $this->assertStringStartsWith('SAL-', $sale->invoice_number);
    }

    public function test_create_sale_creates_items_and_decreases_inventory(): void
    {
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->stockProduct($product->id, 50);

        $sale = $this->saleService->createSale(
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 10, 'sale_rate' => 80.00, 'mrp' => 100.00]]
        );

        // Sale created
        $this->assertNotNull($sale->id);
        $this->assertEquals(800.00, $sale->total_amount);

        // Items created
        $this->assertCount(1, $sale->items);
        $this->assertEquals(10, $sale->items[0]->quantity);

        // Inventory decreased
        $inventory = Inventory::where('product_id', $product->id)->first();
        $this->assertEquals(40, $inventory->quantity);
    }

    public function test_create_sale_adds_debit_to_customer_ledger(): void
    {
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->stockProduct($product->id);

        $sale = $this->saleService->createSale(
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 5, 'sale_rate' => 80.00]]
        );

        $ledgerEntry = CustomerLedger::where('customer_id', $customer->id)->first();
        $this->assertNotNull($ledgerEntry);
        $this->assertEquals('debit', $ledgerEntry->type);
        $this->assertEquals(400.00, $ledgerEntry->amount);

        $customer->refresh();
        $this->assertEquals(400.00, $customer->current_balance);
    }

    public function test_create_sale_blocks_below_cost_selling(): void
    {
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->stockProduct($product->id, 50, 100, 60, 80);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('below getting rate');

        $this->saleService->createSale(
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 5, 'sale_rate' => 50.00]] // Below getting rate of 60
        );
    }

    public function test_create_sale_with_discount_checks_net_rate(): void
    {
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->stockProduct($product->id, 50, 100, 60, 80);

        // Sale rate 80, discount 25 → net rate 55 < getting rate 60 → should fail
        $this->expectException(\RuntimeException::class);

        $this->saleService->createSale(
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 5, 'sale_rate' => 80.00, 'discount' => 25.00]]
        );
    }

    public function test_create_sale_with_multiple_items(): void
    {
        $product1 = $this->createProduct('Product A', 'PA-001');
        $product2 = $this->createProduct('Product B', 'PB-001');
        $customer = $this->createCustomer();
        $this->stockProduct($product1->id, 50, 100, 40, 70);
        $this->stockProduct($product2->id, 30, 200, 120, 160);

        $sale = $this->saleService->createSale(
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [
                ['product_id' => $product1->id, 'quantity' => 5, 'sale_rate' => 70.00],
                ['product_id' => $product2->id, 'quantity' => 3, 'sale_rate' => 160.00],
            ]
        );

        // Total: 5*70 + 3*160 = 350 + 480 = 830
        $this->assertEquals(830.00, $sale->total_amount);
        $this->assertCount(2, $sale->items);

        // Check inventory
        $this->assertEquals(45, Inventory::where('product_id', $product1->id)->value('quantity'));
        $this->assertEquals(27, Inventory::where('product_id', $product2->id)->value('quantity'));
    }

    public function test_update_sale_reverses_and_reapplies_effects(): void
    {
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->stockProduct($product->id, 50);

        $sale = $this->saleService->createSale(
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 10, 'sale_rate' => 80.00]]
        );

        // Inventory should be 40 after sale
        $this->assertEquals(40, Inventory::where('product_id', $product->id)->value('quantity'));

        // Update sale to 5 units instead of 10
        $updatedSale = $this->saleService->updateSale(
            $sale,
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 5, 'sale_rate' => 80.00]]
        );

        // Inventory: reversed 10, decreased 5 → 50 - 5 = 45
        $this->assertEquals(45, Inventory::where('product_id', $product->id)->value('quantity'));

        // Ledger: old debit deleted, new debit for 400
        $this->assertEquals(400.00, $updatedSale->total_amount);
        $customer->refresh();
        $this->assertEquals(400.00, $customer->current_balance);
    }

    public function test_delete_sale_restores_inventory_and_ledger(): void
    {
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->stockProduct($product->id, 50);

        $sale = $this->saleService->createSale(
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 10, 'sale_rate' => 80.00]]
        );

        $this->saleService->deleteSale($sale);

        // Inventory restored
        $this->assertEquals(50, Inventory::where('product_id', $product->id)->value('quantity'));

        // Sale deleted
        $this->assertNull(Sale::find($sale->id));

        // Ledger entry deleted, balance back to 0
        $customer->refresh();
        $this->assertEquals(0.00, $customer->current_balance);
    }

    public function test_invoice_number_increments_per_day(): void
    {
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->stockProduct($product->id, 100);

        $sale1 = $this->saleService->createSale(
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 1, 'sale_rate' => 80.00]]
        );

        $sale2 = $this->saleService->createSale(
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 1, 'sale_rate' => 80.00]]
        );

        // Both should have same date prefix but different sequence numbers
        $this->assertStringEndsWith('001', $sale1->invoice_number);
        $this->assertStringEndsWith('002', $sale2->invoice_number);
    }

    public function test_getting_rate_captured_from_inventory_at_sale_time(): void
    {
        $product = $this->createProduct();
        $customer = $this->createCustomer();
        $this->stockProduct($product->id, 50, 100, 55, 80);

        $sale = $this->saleService->createSale(
            ['customer_id' => $customer->id, 'sale_date' => '2026-05-31'],
            [['product_id' => $product->id, 'quantity' => 5, 'sale_rate' => 80.00]]
        );

        $saleItem = SaleItem::where('sale_id', $sale->id)->first();
        $this->assertEquals(55.00, $saleItem->getting_rate);
    }
}
