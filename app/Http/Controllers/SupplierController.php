<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\SupplierLedgerService;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query();
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%');
        }
        $suppliers = $query->latest()->paginate(15)->appends($request->query());
        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.create');
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        Supplier::create($request->all());
        return redirect()->route('suppliers.index')->with('success', 'Supplier added successfully!');
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $supplier->update($request->all());
        return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully!');
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchases()->exists()) {
            return back()->with('error', 'Cannot delete supplier with existing purchases.');
        }
        $supplier->delete();
        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted successfully!');
    }

    public function ledger(Supplier $supplier, Request $request, SupplierLedgerService $ledgerService)
    {
        $ledger = $ledgerService->getLedger(
            $supplier->id,
            $request->from_date,
            $request->to_date
        );
        return view('suppliers.ledger', compact('supplier', 'ledger'));
    }
}
