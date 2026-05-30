<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\ReturnService;
use Illuminate\Http\Request;

class SaleReturnController extends Controller
{
    public function index()
    {
        $returns = SaleReturn::with('sale', 'customer')->latest('return_date')->latest('id')->paginate(15);
        return view('sale-returns.index', compact('returns'));
    }

    public function create(Request $request)
    {
        $sales = Sale::with('customer')->latest('sale_date')->get();
        $selectedSale = null;
        if ($request->sale_id) {
            $selectedSale = Sale::with('items.product')->find($request->sale_id);
        }
        return view('sale-returns.create', compact('sales', 'selectedSale'));
    }

    public function store(Request $request, ReturnService $returnService)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'return_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.sale_rate' => 'required|numeric|min:0',
        ]);

        $sale = Sale::find($request->sale_id);
        try {
            $returnService->createSaleReturn(
                array_merge($request->only(['sale_id', 'return_date', 'reason']), ['customer_id' => $sale->customer_id]),
                $request->items
            );
            return redirect()->route('sale-returns.index')->with('success', 'Sale return processed successfully!');
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(SaleReturn $saleReturn, ReturnService $returnService)
    {
        $returnNumber = $saleReturn->return_number;
        $returnService->deleteSaleReturn($saleReturn);

        return redirect()->route('sale-returns.index')->with('success', 'Sale return ' . $returnNumber . ' deleted successfully.');
    }
}
