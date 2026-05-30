<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Services\ReturnService;
use Illuminate\Http\Request;

class PurchaseReturnController extends Controller
{
    public function index()
    {
        $returns = PurchaseReturn::with('purchase', 'supplier')->latest('return_date')->latest('id')->paginate(15);
        return view('purchase-returns.index', compact('returns'));
    }

    public function create(Request $request)
    {
        $purchases = Purchase::with('supplier')->latest('purchase_date')->get();
        $selectedPurchase = null;
        if ($request->purchase_id) {
            $selectedPurchase = Purchase::with('items.product')->find($request->purchase_id);
        }
        return view('purchase-returns.create', compact('purchases', 'selectedPurchase'));
    }

    public function store(Request $request, ReturnService $returnService)
    {
        $request->validate([
            'purchase_id' => 'required|exists:purchases,id',
            'return_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.getting_rate' => 'required|numeric|min:0',
        ]);

        $purchase = Purchase::find($request->purchase_id);
        try {
            $returnService->createPurchaseReturn(
                array_merge($request->only(['purchase_id', 'return_date', 'reason']), ['supplier_id' => $purchase->supplier_id]),
                $request->items
            );
            return redirect()->route('purchase-returns.index')->with('success', 'Purchase return processed successfully!');
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(PurchaseReturn $purchaseReturn, ReturnService $returnService)
    {
        $returnNumber = $purchaseReturn->return_number;
        $returnService->deletePurchaseReturn($purchaseReturn);

        return redirect()->route('purchase-returns.index')->with('success', 'Purchase return ' . $returnNumber . ' deleted successfully.');
    }
}
