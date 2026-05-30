<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\CustomerLedger;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StatementController extends Controller
{
    public function index(Request $request)
    {
        $currentYear = Carbon::now()->month >= 4 ? Carbon::now()->year : Carbon::now()->year - 1;
        $year = $request->year ?? $currentYear;

        $fromDate = Carbon::create($year, 4, 1)->format('Y-m-d');
        $toDate = Carbon::create($year + 1, 3, 31)->format('Y-m-d');

        // Pre-aggregate sales by customer
        $salesByCustomer = Sale::where('sale_date', '>=', $fromDate)
            ->where('sale_date', '<=', $toDate)
            ->selectRaw('customer_id, SUM(total_amount) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        // Pre-aggregate returns by customer
        $returnsByCustomer = SaleReturn::where('return_date', '>=', $fromDate)
            ->where('return_date', '<=', $toDate)
            ->selectRaw('customer_id, SUM(total_amount) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        // Pre-aggregate payments by customer
        $paymentsByCustomer = CustomerLedger::where('reference_type', 'payment')
            ->where('transaction_date', '>=', $fromDate)
            ->where('transaction_date', '<=', $toDate)
            ->selectRaw('customer_id, SUM(amount) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        // Get opening balances — last ledger entry before the period for each customer
        $openingBalances = CustomerLedger::where('transaction_date', '<', $fromDate)
            ->selectRaw('customer_id, running_balance')
            ->whereIn('id', function ($q) use ($fromDate) {
                $q->selectRaw('MAX(id)')
                  ->from('customer_ledger')
                  ->where('transaction_date', '<', $fromDate)
                  ->groupBy('customer_id');
            })
            ->pluck('running_balance', 'customer_id');

        $customers = Customer::all()->map(function ($customer) use ($salesByCustomer, $returnsByCustomer, $paymentsByCustomer, $openingBalances) {
            $totalSales = (float) ($salesByCustomer[$customer->id] ?? 0);
            $totalReturns = (float) ($returnsByCustomer[$customer->id] ?? 0);
            $totalPayments = (float) ($paymentsByCustomer[$customer->id] ?? 0);
            $openingBalance = (float) ($openingBalances[$customer->id] ?? 0);
            $closingBalance = $openingBalance + $totalSales - $totalReturns - $totalPayments;

            return (object) [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'opening_balance' => $openingBalance,
                'total_sales' => $totalSales,
                'total_returns' => $totalReturns,
                'total_payments' => $totalPayments,
                'closing_balance' => $closingBalance,
            ];
        })->filter(fn($c) => $c->total_sales > 0 || $c->total_returns > 0 || $c->total_payments > 0 || $c->opening_balance != 0);

        $years = range($currentYear, $currentYear - 3, -1);

        return view('reports.statement', compact('customers', 'year', 'years', 'fromDate', 'toDate'));
    }
}
