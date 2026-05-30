<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\LedgerService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%');
        }
        $customers = $query->latest()->paginate(15)->appends($request->query());
        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);
        Customer::create($request->all());
        return redirect()->route('customers.index')->with('success', 'Customer added successfully!');
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);
        $customer->update($request->all());
        return redirect()->route('customers.index')->with('success', 'Customer updated successfully!');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->sales()->exists()) {
            return back()->with('error', 'Cannot delete customer with existing sales.');
        }
        $customer->delete();
        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully!');
    }

    public function ledger(Customer $customer, Request $request)
    {
        $ledger = app(LedgerService::class)->getLedger(
            $customer->id,
            $request->from_date,
            $request->to_date
        );
        return view('customers.ledger', compact('customer', 'ledger'));
    }
}
