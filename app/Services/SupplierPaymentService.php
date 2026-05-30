<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SupplierPaymentService
{
    public function __construct(
        protected SupplierLedgerService $supplierLedgerService
    ) {}

    /**
     * Record a payment to a supplier.
     * Adds a credit entry to the supplier's ledger, reducing outstanding balance.
     */
    public function recordPayment(
        int $supplierId,
        float $amount,
        string $date,
        ?string $notes = null
    ): void {
        $this->supplierLedgerService->addEntry(
            supplierId: $supplierId,
            type: 'credit',
            amount: $amount,
            referenceType: 'payment',
            referenceId: null,
            description: $notes ?? 'Payment made to supplier',
            date: $date
        );
    }
}
