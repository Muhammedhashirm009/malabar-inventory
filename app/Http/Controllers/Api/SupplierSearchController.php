<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierSearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = $request->get('q', '');
        if (strlen($q) < 1) return response()->json([]);

        return Supplier::where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'phone', 'current_balance']);
    }
}
