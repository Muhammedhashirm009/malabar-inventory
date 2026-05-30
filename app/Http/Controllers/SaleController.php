<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Customer;
use App\Services\SaleService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $query = Sale::with('customer');
        if ($request->search) {
            $query->where('invoice_number', 'like', '%' . $request->search . '%');
        }
        $sales = $query->latest('sale_date')->latest('id')->paginate(15)->appends($request->query());
        return view('sales.index', compact('sales'));
    }

    public function create()
    {
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        return view('sales.create', compact('customers'));
    }

    public function store(Request $request, SaleService $saleService)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sale_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.sale_rate' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        try {
            $sale = $saleService->createSale(
                $request->only(['customer_id', 'sale_date', 'notes']),
                $request->items
            );
            return redirect()->route('sales.show', $sale)->with('success', 'Sale created successfully! Invoice: ' . $sale->invoice_number);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Sale $sale)
    {
        $sale->load('customer', 'items.product');
        return view('sales.show', compact('sale'));
    }

    public function edit(Sale $sale)
    {
        $sale->load('customer', 'items.product');
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        return view('sales.edit', compact('sale', 'customers'));
    }

    public function update(Request $request, Sale $sale, SaleService $saleService)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sale_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.sale_rate' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        try {
            $saleService->updateSale(
                $sale,
                $request->only(['customer_id', 'sale_date', 'notes']),
                $request->items
            );
            return redirect()->route('sales.show', $sale)->with('success', 'Sale updated successfully! Invoice: ' . $sale->invoice_number);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(Sale $sale, SaleService $saleService)
    {
        $invoiceNumber = $sale->invoice_number;
        $saleService->deleteSale($sale);

        return redirect()->route('sales.index')->with('success', 'Sale invoice ' . $invoiceNumber . ' deleted successfully.');
    }

    public function print(Sale $sale)
    {
        $sale->load('customer', 'items.product');
        return view('sales.print', compact('sale'));
    }
}
