<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\SupplierLedgerService;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SupplierLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SupplierLedgerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SupplierLedgerService::class);
    }

    private function createSupplier(array $overrides = []): Supplier
    {
        return Supplier::create(array_merge([
            'name' => 'Test Supplier',
            'phone' => '9876543210',
            'is_active' => true,
            'current_balance' => 0,
        ], $overrides));
    }

    public function test_add_debit_entry_increases_balance(): void
    {
        $supplier = $this->createSupplier();

        $entry = $this->service->addEntry(
            supplierId: $supplier->id,
            type: 'debit',
            amount: 5000.00,
            referenceType: 'purchase',
            referenceId: 1,
            description: 'Purchase #PUR-20260531-001',
            date: '2026-05-31'
        );

        $this->assertEquals(5000.00, $entry->running_balance);

        $supplier->refresh();
        $this->assertEquals(5000.00, $supplier->current_balance);
    }

    public function test_add_credit_entry_decreases_balance(): void
    {
        $supplier = $this->createSupplier();

        $this->service->addEntry($supplier->id, 'debit', 5000.00, 'purchase', 1, 'Purchase #1', '2026-05-31');
        $entry = $this->service->addEntry($supplier->id, 'credit', 2000.00, 'payment', null, 'Payment to supplier', '2026-05-31');

        $this->assertEquals(3000.00, $entry->running_balance);

        $supplier->refresh();
        $this->assertEquals(3000.00, $supplier->current_balance);
    }

    public function test_running_balance_accumulates_correctly(): void
    {
        $supplier = $this->createSupplier();

        $this->service->addEntry($supplier->id, 'debit', 10000.00, 'purchase', 1, 'Purchase 1', '2026-05-01');
        $this->service->addEntry($supplier->id, 'credit', 3000.00, 'payment', null, 'Payment', '2026-05-05');
        $this->service->addEntry($supplier->id, 'debit', 5000.00, 'purchase', 2, 'Purchase 2', '2026-05-10');
        $entry = $this->service->addEntry($supplier->id, 'credit', 2000.00, 'purchase_return', 1, 'Return', '2026-05-15');

        // 10000 - 3000 + 5000 - 2000 = 10000
        $this->assertEquals(10000.00, $entry->running_balance);

        $supplier->refresh();
        $this->assertEquals(10000.00, $supplier->current_balance);
    }

    public function test_backdated_entry_triggers_recalculation(): void
    {
        $supplier = $this->createSupplier();

        $this->service->addEntry($supplier->id, 'debit', 5000.00, 'purchase', 1, 'Purchase', '2026-05-10');
        $this->service->addEntry($supplier->id, 'debit', 3000.00, 'purchase', 2, 'Purchase', '2026-05-20');

        // Backdated credit
        $this->service->addEntry($supplier->id, 'credit', 2000.00, 'payment', null, 'Backdated payment', '2026-05-05');

        $entries = SupplierLedger::where('supplier_id', $supplier->id)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $this->assertEquals(-2000.00, (float) $entries[0]->running_balance);
        $this->assertEquals(3000.00, (float) $entries[1]->running_balance);
        $this->assertEquals(6000.00, (float) $entries[2]->running_balance);

        $supplier->refresh();
        $this->assertEquals(6000.00, $supplier->current_balance);
    }

    public function test_delete_entry_recalculates_balances(): void
    {
        $supplier = $this->createSupplier();

        $this->service->addEntry($supplier->id, 'debit', 5000.00, 'purchase', 1, 'Purchase 1', '2026-05-01');
        $this->service->addEntry($supplier->id, 'debit', 3000.00, 'purchase', 2, 'Purchase 2', '2026-05-02');

        $this->service->deleteEntry('purchase', 1, $supplier->id);

        $entries = SupplierLedger::where('supplier_id', $supplier->id)->get();
        $this->assertCount(1, $entries);

        $supplier->refresh();
        $this->assertEquals(3000.00, $supplier->current_balance);
    }

    public function test_get_balance_returns_zero_for_new_supplier(): void
    {
        $supplier = $this->createSupplier();

        $this->assertEquals(0.0, $this->service->getBalance($supplier->id));
    }

    public function test_get_ledger_filters_by_date_range(): void
    {
        $supplier = $this->createSupplier();

        $this->service->addEntry($supplier->id, 'debit', 1000.00, 'purchase', 1, 'P1', '2026-05-01');
        $this->service->addEntry($supplier->id, 'debit', 2000.00, 'purchase', 2, 'P2', '2026-05-15');
        $this->service->addEntry($supplier->id, 'debit', 3000.00, 'purchase', 3, 'P3', '2026-05-25');

        $entries = $this->service->getLedger($supplier->id, '2026-05-10', '2026-05-20');
        $this->assertCount(1, $entries);
        $this->assertEquals(2000.00, (float) $entries->first()->amount);
    }
}
