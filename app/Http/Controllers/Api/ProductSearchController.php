<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Http\Request;

class ProductSearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = $request->get('q', '');
        if (strlen($q) < 1) return response()->json([]);

        $products = Product::where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('sku', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get();

        // Pre-fetch inventory for all matched products in a single query
        $productIds = $products->pluck('id');
        $inventories = Inventory::whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        return response()->json($products->map(function ($p) use ($inventories) {
            $inv = $inventories[$p->id] ?? null;
            return [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'unit' => $p->unit,
                'mrp' => (float) $p->mrp,
                'getting_rate' => $inv ? (float) $inv->getting_rate : 0,
                'sale_rate' => $inv ? (float) $inv->sale_rate : 0,
                'stock' => $inv ? (float) $inv->quantity : 0,
            ];
        }));
    }
}
