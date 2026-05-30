<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Record a payment from a customer.
     * Adds a credit entry to the customer's ledger, reducing their outstanding balance.
     */
    public function recordPayment(
        int $customerId,
        float $amount,
        string $date,
        ?string $notes = null
    ): void {
        $this->ledgerService->addEntry(
            customerId: $customerId,
            type: 'credit',
            amount: $amount,
            referenceType: 'payment',
            referenceId: null,
            description: $notes ?? 'Payment received',
            date: $date
        );
    }
}
