<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SaleItem;
use Illuminate\Http\Request;

class CustomerLastDiscountController extends Controller
{
    public function __invoke(Customer $customer, Request $request)
    {
        $productId = $request->get('product_id');
        if (!$productId) {
            return response()->json(['discount' => 0]);
        }

        // Find the latest sale item for this customer and product
        $lastItem = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.customer_id', $customer->id)
            ->where('sale_items.product_id', $productId)
            ->orderByDesc('sales.sale_date')
            ->orderByDesc('sale_items.id')
            ->select('sale_items.discount')
            ->first();

        return response()->json([
            'discount' => $lastItem ? (float) $lastItem->discount : 0
        ]);
    }
}
