<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\InventoryService;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InventoryService::class);
    }

    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Test Product',
            'sku' => 'TP-001',
            'unit' => 'pcs',
            'mrp' => 100.00,
            'is_active' => true,
        ], $overrides));
    }

    public function test_increase_stock_creates_new_inventory_record(): void
    {
        $product = $this->createProduct();

        $this->service->increaseStock($product->id, 10, 100.00, 60.00, 80.00);

        $inventory = Inventory::where('product_id', $product->id)->first();
        $this->assertNotNull($inventory);
        $this->assertEquals(10, $inventory->quantity);
        $this->assertEquals(100.00, $inventory->mrp);
        $this->assertEquals(60.00, $inventory->getting_rate);
        $this->assertEquals(80.00, $inventory->sale_rate);
    }

    public function test_increase_stock_adds_to_existing_quantity(): void
    {
        $product = $this->createProduct();

        $this->service->increaseStock($product->id, 10, 100.00, 60.00, 80.00);
        $this->service->increaseStock($product->id, 5, 100.00, 70.00, 85.00);

        $inventory = Inventory::where('product_id', $product->id)->first();
        $this->assertEquals(15, $inventory->quantity);
    }

    public function test_weighted_average_getting_rate_calculation(): void
    {
        $product = $this->createProduct();

        // First stock: 10 units at ₹60
        $this->service->increaseStock($product->id, 10, 100.00, 60.00, 80.00);
        // Second stock: 10 units at ₹80
        // Weighted average = (10 * 60 + 10 * 80) / 20 = 1400 / 20 = 70
        $this->service->increaseStock($product->id, 10, 100.00, 80.00, 85.00);

        $inventory = Inventory::where('product_id', $product->id)->first();
        $this->assertEquals(20, $inventory->quantity);
        $this->assertEquals(70.00, $inventory->getting_rate);
    }

    public function test_weighted_average_with_unequal_quantities(): void
    {
        $product = $this->createProduct();

        // 20 units at ₹50
        $this->service->increaseStock($product->id, 20, 100.00, 50.00, 80.00);
        // 10 units at ₹80
        // Weighted average = (20 * 50 + 10 * 80) / 30 = 1800 / 30 = 60
        $this->service->increaseStock($product->id, 10, 100.00, 80.00, 85.00);

        $inventory = Inventory::where('product_id', $product->id)->first();
        $this->assertEquals(30, $inventory->quantity);
        $this->assertEquals(60.00, $inventory->getting_rate);
    }

    public function test_decrease_stock_reduces_quantity(): void
    {
        $product = $this->createProduct();

        $this->service->increaseStock($product->id, 10, 100.00, 60.00, 80.00);
        $this->service->decreaseStock($product->id, 3);

        $inventory = Inventory::where('product_id', $product->id)->first();
        $this->assertEquals(7, $inventory->quantity);
    }

    public function test_decrease_stock_throws_on_insufficient_stock(): void
    {
        $product = $this->createProduct();

        $this->service->increaseStock($product->id, 5, 100.00, 60.00, 80.00);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->decreaseStock($product->id, 10);
    }

    public function test_decrease_stock_throws_when_no_inventory_exists(): void
    {
        $product = $this->createProduct();

        $this->expectException(\RuntimeException::class);

        $this->service->decreaseStock($product->id, 1);
    }

    public function test_get_stock_returns_inventory(): void
    {
        $product = $this->createProduct();
        $this->service->increaseStock($product->id, 10, 100.00, 60.00, 80.00);

        $inventory = $this->service->getStock($product->id);

        $this->assertNotNull($inventory);
        $this->assertEquals(10, $inventory->quantity);
    }

    public function test_get_stock_returns_null_when_no_inventory(): void
    {
        $product = $this->createProduct();

        $this->assertNull($this->service->getStock($product->id));
    }

    public function test_has_stock_returns_true_when_sufficient(): void
    {
        $product = $this->createProduct();
        $this->service->increaseStock($product->id, 10, 100.00, 60.00, 80.00);

        $this->assertTrue($this->service->hasStock($product->id, 10));
        $this->assertTrue($this->service->hasStock($product->id, 5));
    }

    public function test_has_stock_returns_false_when_insufficient(): void
    {
        $product = $this->createProduct();
        $this->service->increaseStock($product->id, 5, 100.00, 60.00, 80.00);

        $this->assertFalse($this->service->hasStock($product->id, 10));
    }

    public function test_reverse_increase_reduces_stock(): void
    {
        $product = $this->createProduct();

        $this->service->increaseStock($product->id, 10, 100.00, 60.00, 80.00);
        $this->service->reverseIncrease($product->id, 4);

        $inventory = Inventory::where('product_id', $product->id)->first();
        $this->assertEquals(6, $inventory->quantity);
    }

    public function test_reverse_increase_does_not_go_below_zero(): void
    {
        $product = $this->createProduct();

        $this->service->increaseStock($product->id, 5, 100.00, 60.00, 80.00);
        $this->service->reverseIncrease($product->id, 10);

        $inventory = Inventory::where('product_id', $product->id)->first();
        $this->assertEquals(0, $inventory->quantity);
    }

    public function test_reverse_decrease_adds_stock_back(): void
    {
        $product = $this->createProduct();

        $this->service->increaseStock($product->id, 10, 100.00, 60.00, 80.00);
        $this->service->decreaseStock($product->id, 7);
        $this->service->reverseDecrease($product->id, 7);

        $inventory = Inventory::where('product_id', $product->id)->first();
        $this->assertEquals(10, $inventory->quantity);
    }

    public function test_mrp_and_sale_rate_updated_on_new_stock(): void
    {
        $product = $this->createProduct();

        $this->service->increaseStock($product->id, 10, 100.00, 60.00, 80.00);
        $this->service->increaseStock($product->id, 5, 120.00, 70.00, 95.00);

        $inventory = Inventory::where('product_id', $product->id)->first();
        // MRP and sale_rate should reflect the latest values
        $this->assertEquals(120.00, $inventory->mrp);
        $this->assertEquals(95.00, $inventory->sale_rate);
    }
}
