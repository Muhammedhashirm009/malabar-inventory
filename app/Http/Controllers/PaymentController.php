<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = CustomerLedger::with('customer')
            ->where('reference_type', 'payment')
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(15);
        return view('payments.index', compact('payments'));
    }

    public function create(Request $request)
    {
        $selectedCustomer = null;
        if ($request->customer_id) {
            $selectedCustomer = Customer::find($request->customer_id);
        }
        return view('payments.create', compact('selectedCustomer'));
    }

    public function store(Request $request, PaymentService $paymentService)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
        ]);

        $paymentService->recordPayment(
            $request->customer_id,
            $request->amount,
            $request->payment_date,
            $request->notes
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully!',
                'redirect' => route('payments.index')
            ]);
        }

        return redirect()->route('payments.index')->with('success', 'Payment recorded successfully!');
    }
}
