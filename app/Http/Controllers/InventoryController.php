<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Inventory::with('product');
        if ($request->search) {
            $query->whereHas('product', fn($q) => $q->where('name', 'like', '%' . $request->search . '%'));
        }
        $inventory = $query->orderBy('quantity', 'asc')->paginate(20)->appends($request->query());
        $totalValue = Inventory::selectRaw('SUM(getting_rate * quantity) as total')->value('total') ?? 0;
        return view('inventory.index', compact('inventory', 'totalValue'));
    }
}
