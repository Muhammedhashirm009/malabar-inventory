<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Services\SupplierPaymentService;
use Illuminate\Http\Request;

class SupplierPaymentController extends Controller
{
    public function index()
    {
        $payments = SupplierLedger::with('supplier')
            ->where('reference_type', 'payment')
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(15);
        return view('supplier-payments.index', compact('payments'));
    }

    public function create(Request $request)
    {
        $selectedSupplier = null;
        if ($request->supplier_id) {
            $selectedSupplier = Supplier::find($request->supplier_id);
        }
        return view('supplier-payments.create', compact('selectedSupplier'));
    }

    public function store(Request $request, SupplierPaymentService $paymentService)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
        ]);

        $paymentService->recordPayment(
            $request->supplier_id,
            $request->amount,
            $request->payment_date,
            $request->notes
        );

        return redirect()->route('supplier-payments.index')->with('success', 'Supplier payment recorded successfully!');
    }
}
