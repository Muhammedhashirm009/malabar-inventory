<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\LedgerService;
use App\Models\Customer;
use App\Models\CustomerLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LedgerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LedgerService::class);
    }

    private function createCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Test Customer',
            'phone' => '9876543210',
            'is_active' => true,
            'current_balance' => 0,
            'credit_limit' => 50000,
        ], $overrides));
    }

    public function test_add_debit_entry_increases_balance(): void
    {
        $customer = $this->createCustomer();

        $entry = $this->service->addEntry(
            customerId: $customer->id,
            type: 'debit',
            amount: 1000.00,
            referenceType: 'sale',
            referenceId: 1,
            description: 'Sale #SAL-20260531-001',
            date: '2026-05-31'
        );

        $this->assertEquals(1000.00, $entry->running_balance);
        $this->assertEquals('debit', $entry->type);

        $customer->refresh();
        $this->assertEquals(1000.00, $customer->current_balance);
    }

    public function test_add_credit_entry_decreases_balance(): void
    {
        $customer = $this->createCustomer();

        // First add a debit
        $this->service->addEntry(
            customerId: $customer->id,
            type: 'debit',
            amount: 1000.00,
            referenceType: 'sale',
            referenceId: 1,
            description: 'Sale #1',
            date: '2026-05-31'
        );

        // Then add a credit (payment)
        $entry = $this->service->addEntry(
            customerId: $customer->id,
            type: 'credit',
            amount: 400.00,
            referenceType: 'payment',
            referenceId: null,
            description: 'Payment received',
            date: '2026-05-31'
        );

        $this->assertEquals(600.00, $entry->running_balance);

        $customer->refresh();
        $this->assertEquals(600.00, $customer->current_balance);
    }

    public function test_running_balance_accumulates_correctly(): void
    {
        $customer = $this->createCustomer();

        $this->service->addEntry($customer->id, 'debit', 500.00, 'sale', 1, 'Sale 1', '2026-05-01');
        $this->service->addEntry($customer->id, 'debit', 300.00, 'sale', 2, 'Sale 2', '2026-05-02');
        $this->service->addEntry($customer->id, 'credit', 200.00, 'payment', null, 'Payment', '2026-05-03');
        $entry = $this->service->addEntry($customer->id, 'debit', 100.00, 'sale', 3, 'Sale 3', '2026-05-04');

        // 500 + 300 - 200 + 100 = 700
        $this->assertEquals(700.00, $entry->running_balance);

        $customer->refresh();
        $this->assertEquals(700.00, $customer->current_balance);
    }

    public function test_backdated_entry_triggers_recalculation(): void
    {
        $customer = $this->createCustomer();

        $this->service->addEntry($customer->id, 'debit', 500.00, 'sale', 1, 'Sale 1', '2026-05-10');
        $this->service->addEntry($customer->id, 'debit', 300.00, 'sale', 2, 'Sale 2', '2026-05-20');

        // Add a backdated entry (before the first entry)
        $this->service->addEntry($customer->id, 'credit', 200.00, 'payment', null, 'Backdated payment', '2026-05-05');

        // After recalculation, all running balances should be correct
        $entries = CustomerLedger::where('customer_id', $customer->id)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        // Entry 1 (May 5): credit 200 → -200
        $this->assertEquals(-200.00, (float) $entries[0]->running_balance);
        // Entry 2 (May 10): debit 500 → 300
        $this->assertEquals(300.00, (float) $entries[1]->running_balance);
        // Entry 3 (May 20): debit 300 → 600
        $this->assertEquals(600.00, (float) $entries[2]->running_balance);

        $customer->refresh();
        $this->assertEquals(600.00, $customer->current_balance);
    }

    public function test_delete_entry_recalculates_balances(): void
    {
        $customer = $this->createCustomer();

        $this->service->addEntry($customer->id, 'debit', 500.00, 'sale', 1, 'Sale 1', '2026-05-01');
        $this->service->addEntry($customer->id, 'debit', 300.00, 'sale', 2, 'Sale 2', '2026-05-02');
        $this->service->addEntry($customer->id, 'debit', 200.00, 'sale', 3, 'Sale 3', '2026-05-03');

        // Delete the middle entry
        $this->service->deleteEntry('sale', 2, $customer->id);

        // Should have 2 entries left
        $entries = CustomerLedger::where('customer_id', $customer->id)->orderBy('id')->get();
        $this->assertCount(2, $entries);

        // Balance should be recalculated: 500 + 200 = 700
        $customer->refresh();
        $this->assertEquals(700.00, $customer->current_balance);
    }

    public function test_get_balance_returns_zero_for_new_customer(): void
    {
        $customer = $this->createCustomer();

        $this->assertEquals(0.0, $this->service->getBalance($customer->id));
    }

    public function test_get_balance_returns_latest_balance(): void
    {
        $customer = $this->createCustomer();

        $this->service->addEntry($customer->id, 'debit', 500.00, 'sale', 1, 'Sale 1', '2026-05-01');
        $this->service->addEntry($customer->id, 'credit', 200.00, 'payment', null, 'Payment', '2026-05-02');

        $this->assertEquals(300.00, $this->service->getBalance($customer->id));
    }

    public function test_get_ledger_returns_all_entries(): void
    {
        $customer = $this->createCustomer();

        $this->service->addEntry($customer->id, 'debit', 500.00, 'sale', 1, 'Sale 1', '2026-05-01');
        $this->service->addEntry($customer->id, 'debit', 300.00, 'sale', 2, 'Sale 2', '2026-05-15');
        $this->service->addEntry($customer->id, 'credit', 200.00, 'payment', null, 'Payment', '2026-05-20');

        $entries = $this->service->getLedger($customer->id);
        $this->assertCount(3, $entries);
    }

    public function test_get_ledger_filters_by_date_range(): void
    {
        $customer = $this->createCustomer();

        $this->service->addEntry($customer->id, 'debit', 100.00, 'sale', 1, 'Sale 1', '2026-05-01');
        $this->service->addEntry($customer->id, 'debit', 200.00, 'sale', 2, 'Sale 2', '2026-05-15');
        $this->service->addEntry($customer->id, 'debit', 300.00, 'sale', 3, 'Sale 3', '2026-05-25');

        $entries = $this->service->getLedger($customer->id, '2026-05-10', '2026-05-20');
        $this->assertCount(1, $entries);
        $this->assertEquals(200.00, (float) $entries->first()->amount);
    }
}
