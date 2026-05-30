<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $query = Purchase::with('supplier');
        if ($request->search) {
            $query->where('invoice_number', 'like', '%' . $request->search . '%');
        }
        $purchases = $query->latest('purchase_date')->latest('id')->paginate(15)->appends($request->query());
        return view('purchases.index', compact('purchases'));
    }

    public function create()
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        return view('purchases.create', compact('suppliers'));
    }

    public function store(Request $request, PurchaseService $purchaseService)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.mrp' => 'required|numeric|min:0',
            'items.*.getting_rate' => 'required|numeric|min:0',
            'items.*.sale_rate' => 'required|numeric|min:0',
        ]);

        try {
            $purchase = $purchaseService->createPurchase(
                $request->only(['supplier_id', 'purchase_date', 'notes']),
                $request->items
            );
            return redirect()->route('purchases.show', $purchase)->with('success', 'Purchase created successfully! Invoice: ' . $purchase->invoice_number);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Purchase $purchase)
    {
        $purchase->load('supplier', 'items.product');
        return view('purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase)
    {
        $purchase->load('supplier', 'items.product');
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        return view('purchases.edit', compact('purchase', 'suppliers'));
    }

    public function update(Request $request, Purchase $purchase, PurchaseService $purchaseService)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.mrp' => 'required|numeric|min:0',
            'items.*.getting_rate' => 'required|numeric|min:0',
            'items.*.sale_rate' => 'required|numeric|min:0',
        ]);

        try {
            $purchaseService->updatePurchase(
                $purchase,
                $request->only(['supplier_id', 'purchase_date', 'notes']),
                $request->items
            );
            return redirect()->route('purchases.show', $purchase)->with('success', 'Purchase updated successfully! Invoice: ' . $purchase->invoice_number);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(Purchase $purchase, PurchaseService $purchaseService)
    {
        $invoiceNumber = $purchase->invoice_number;
        $purchaseService->deletePurchase($purchase);

        return redirect()->route('purchases.index')->with('success', 'Purchase invoice ' . $invoiceNumber . ' deleted successfully.');
    }
}
