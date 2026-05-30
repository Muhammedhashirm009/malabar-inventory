<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierLedger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SupplierLedgerService
{
    /**
     * Add a ledger entry for a supplier.
     * Calculates running balance and updates supplier's current balance.
     */
    public function addEntry(
        int $supplierId,
        string $type,
        float $amount,
        string $referenceType,
        ?int $referenceId,
        string $description,
        string $date
    ): SupplierLedger {
        return DB::transaction(function () use (
            $supplierId,
            $type,
            $amount,
            $referenceType,
            $referenceId,
            $description,
            $date
        ) {
            $lastEntry = SupplierLedger::where('supplier_id', $supplierId)
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->first();

            $previousBalance = $lastEntry ? $lastEntry->running_balance : 0;

            // Debit increases balance (we owe them more), credit decreases it
            $runningBalance = $type === 'debit'
                ? $previousBalance + $amount
                : $previousBalance - $amount;

            $ledgerEntry = SupplierLedger::create([
                'supplier_id'      => $supplierId,
                'transaction_date' => $date,
                'type'             => $type,
                'amount'           => $amount,
                'running_balance'  => $runningBalance,
                'reference_type'   => $referenceType,
                'reference_id'     => $referenceId,
                'description'      => $description,
            ]);

            // If this entry was backdated, recalculate all running balances
            if ($lastEntry && $date < $lastEntry->transaction_date) {
                $this->recalculateBalances($supplierId);
            } else {
                // Update supplier's current balance
                Supplier::where('id', $supplierId)->update([
                    'current_balance' => $runningBalance,
                ]);
            }

            return $ledgerEntry;
        });
    }

    /**
     * Get the latest running balance for a supplier.
     */
    public function getBalance(int $supplierId): float
    {
        $lastEntry = SupplierLedger::where('supplier_id', $supplierId)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->first();

        return $lastEntry ? (float) $lastEntry->running_balance : 0.0;
    }

    /**
     * Get ledger entries for a supplier with optional date filtering.
     */
    public function getLedger(
        int $supplierId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): Collection {
        $query = SupplierLedger::where('supplier_id', $supplierId);

        if ($fromDate) {
            $query->where('transaction_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('transaction_date', '<=', $toDate);
        }

        return $query->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Delete a ledger entry and recalculate balances.
     */
    public function deleteEntry(string $referenceType, int $referenceId, int $supplierId): void
    {
        DB::transaction(function () use ($referenceType, $referenceId, $supplierId) {
            SupplierLedger::where('supplier_id', $supplierId)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->delete();

            $this->recalculateBalances($supplierId);
        });
    }

    /**
     * Recalculate running balances for a supplier's ledger.
     */
    public function recalculateBalances(int $supplierId): void
    {
        $entries = SupplierLedger::where('supplier_id', $supplierId)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $balance = 0.0;
        $updates = [];
        foreach ($entries as $entry) {
            if ($entry->type === 'debit') {
                $balance += (float) $entry->amount;
            } else {
                $balance -= (float) $entry->amount;
            }
            if ((float) $entry->running_balance !== $balance) {
                $updates[$entry->id] = $balance;
            }
        }

        // Batch update changed entries
        foreach ($updates as $id => $newBalance) {
            SupplierLedger::where('id', $id)->update(['running_balance' => $newBalance]);
        }

        Supplier::where('id', $supplierId)->update([
            'current_balance' => $balance,
        ]);
    }
}
