<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Purchase;
use App\Models\Inventory;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        $totalProducts = Product::where('is_active', true)->count();
        $totalCustomers = Customer::where('is_active', true)->count();

        $todaySalesAmount = Sale::whereDate('sale_date', $today)->sum('total_amount');
        $todaySalesCount = Sale::whereDate('sale_date', $today)->count();
        $todayPurchasesAmount = Purchase::whereDate('purchase_date', $today)->sum('total_amount');
        $todayPurchasesCount = Purchase::whereDate('purchase_date', $today)->count();

        $monthSales = Sale::whereDate('sale_date', '>=', $monthStart)->sum('total_amount');
        $monthPurchases = Purchase::whereDate('purchase_date', '>=', $monthStart)->sum('total_amount');

        $totalOutstanding = Customer::where('current_balance', '>', 0)->sum('current_balance');

        $totalInventoryValue = Inventory::selectRaw('SUM(getting_rate * quantity) as total')->value('total') ?? 0;

        // Today's profit
        $todayProfit = SaleItem::whereHas('sale', fn($q) => $q->whereDate('sale_date', $today))
            ->selectRaw('SUM((sale_rate - COALESCE(discount, 0) - getting_rate) * quantity) as profit')
            ->value('profit') ?? 0;

        // Monthly chart data (last 6 months) — consolidated into 2 queries instead of 12
        $monthLabels = [];
        $monthlySalesData = [];
        $monthlyPurchasesData = [];
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();

        $salesByMonth = Sale::where('sale_date', '>=', $sixMonthsAgo)
            ->selectRaw("strftime('%Y', sale_date) as y, strftime('%m', sale_date) as m, SUM(total_amount) as total")
            ->groupByRaw("strftime('%Y', sale_date), strftime('%m', sale_date)")
            ->get()
            ->keyBy(fn($r) => intval($r->y) . '-' . intval($r->m));

        $purchasesByMonth = Purchase::where('purchase_date', '>=', $sixMonthsAgo)
            ->selectRaw("strftime('%Y', purchase_date) as y, strftime('%m', purchase_date) as m, SUM(total_amount) as total")
            ->groupByRaw("strftime('%Y', purchase_date), strftime('%m', purchase_date)")
            ->get()
            ->keyBy(fn($r) => intval($r->y) . '-' . intval($r->m));

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $key = $date->year . '-' . $date->month;
            $monthLabels[] = $date->format('M Y');
            $monthlySalesData[] = (float) (isset($salesByMonth[$key]) ? $salesByMonth[$key]->total : 0);
            $monthlyPurchasesData[] = (float) (isset($purchasesByMonth[$key]) ? $purchasesByMonth[$key]->total : 0);
        }

        $recentSales = Sale::with('customer')->latest('sale_date')->latest('id')->take(10)->get();
        $lowStock = Inventory::with('product')->where('quantity', '<', 10)->orderBy('quantity')->take(10)->get();
        $topCustomers = Customer::where('current_balance', '>', 0)->orderByDesc('current_balance')->take(5)->get();

        return view('dashboard.index', compact(
            'totalProducts', 'totalCustomers', 'todaySalesAmount', 'todaySalesCount',
            'todayPurchasesAmount', 'todayPurchasesCount', 'monthSales', 'monthPurchases',
            'totalOutstanding', 'totalInventoryValue', 'todayProfit',
            'monthLabels', 'monthlySalesData', 'monthlyPurchasesData',
            'recentSales', 'lowStock', 'topCustomers'
        ));
    }
}
