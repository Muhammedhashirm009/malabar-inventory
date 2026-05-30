<?php

namespace App\Http\Controllers;

use App\Models\SaleItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Carbon\Carbon;

class MarginReportController extends Controller
{
    public function index(Request $request)
    {
        $fromDate = $request->from_date ?? Carbon::today()->format('Y-m-d');
        $toDate = $request->to_date ?? $fromDate;

        $items = SaleItem::with('product')
            ->whereHas('sale', function ($q) use ($fromDate, $toDate) {
                $q->whereDate('sale_date', '>=', $fromDate)
                  ->whereDate('sale_date', '<=', $toDate);
            })
            ->get()
            ->groupBy('product_id')
            ->map(function ($group) {
                $product = $group->first()->product;
                $qtySold = $group->sum('quantity');
                $revenue = $group->sum(fn($i) => ($i->sale_rate - ($i->discount ?? 0)) * $i->quantity);
                $cost = $group->sum(fn($i) => $i->getting_rate * $i->quantity);
                $profit = $revenue - $cost;
                return (object) [
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'qty_sold' => $qtySold,
                    'avg_sale_rate' => $qtySold > 0 ? $revenue / $qtySold : 0,
                    'avg_getting_rate' => $qtySold > 0 ? $cost / $qtySold : 0,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => $profit,
                    'margin_pct' => $revenue > 0 ? ($profit / $revenue) * 100 : 0,
                ];
            })
            ->sortByDesc('profit')
            ->values();

        $totalRevenue = $items->sum('revenue');
        $totalCost = $items->sum('cost');
        $totalProfit = $items->sum('profit');
        $overallMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        return view('reports.margin', compact(
            'items', 'fromDate', 'toDate',
            'totalRevenue', 'totalCost', 'totalProfit', 'overallMargin'
        ));
    }
}
