<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLedger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /**
     * Add a ledger entry for a customer.
     * Calculates running balance from the last entry and updates the customer's current balance.
     */
    public function addEntry(
        int $customerId,
        string $type,
        float $amount,
        string $referenceType,
        ?int $referenceId,
        string $description,
        string $date
    ): CustomerLedger {
        return DB::transaction(function () use (
            $customerId,
            $type,
            $amount,
            $referenceType,
            $referenceId,
            $description,
            $date
        ) {
            // Get the last running balance for this customer
            $lastEntry = CustomerLedger::where('customer_id', $customerId)
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->first();

            $previousBalance = $lastEntry ? $lastEntry->running_balance : 0;

            // Debit increases the balance (customer owes more), credit decreases it
            $runningBalance = $type === 'debit'
                ? $previousBalance + $amount
                : $previousBalance - $amount;

            $ledgerEntry = CustomerLedger::create([
                'customer_id'      => $customerId,
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
                $this->recalculateBalances($customerId);
            } else {
                // Update the customer's current balance
                Customer::where('id', $customerId)->update([
                    'current_balance' => $runningBalance,
                ]);
            }

            return $ledgerEntry;
        });
    }

    /**
     * Get the latest running balance for a customer.
     */
    public function getBalance(int $customerId): float
    {
        $lastEntry = CustomerLedger::where('customer_id', $customerId)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->first();

        return $lastEntry ? (float) $lastEntry->running_balance : 0.0;
    }

    /**
     * Get ledger entries for a customer with optional date filtering.
     */
    public function getLedger(
        int $customerId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): Collection {
        $query = CustomerLedger::where('customer_id', $customerId);

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
    public function deleteEntry(string $referenceType, int $referenceId, int $customerId): void
    {
        DB::transaction(function () use ($referenceType, $referenceId, $customerId) {
            CustomerLedger::where('customer_id', $customerId)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->delete();

            $this->recalculateBalances($customerId);
        });
    }

    /**
     * Recalculate running balances for a customer's ledger.
     */
    public function recalculateBalances(int $customerId): void
    {
        $entries = CustomerLedger::where('customer_id', $customerId)
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
            CustomerLedger::where('id', $id)->update(['running_balance' => $newBalance]);
        }

        Customer::where('id', $customerId)->update([
            'current_balance' => $balance,
        ]);
    }
}
