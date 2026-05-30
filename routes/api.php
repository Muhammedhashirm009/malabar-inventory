<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductSearchController;
use App\Http\Controllers\Api\CustomerSearchController;
use App\Http\Controllers\Api\SupplierSearchController;
use App\Http\Controllers\Api\CustomerLastDiscountController;

Route::get('products/search', ProductSearchController::class)->name('api.products.search');
Route::get('customers/search', CustomerSearchController::class)->name('api.customers.search');
Route::get('suppliers/search', SupplierSearchController::class)->name('api.suppliers.search');
Route::get('customers/{customer}/last-discount', CustomerLastDiscountController::class)->name('api.customers.last-discount');

Route::get('purchases/{purchase}/items', function (App\Models\Purchase $purchase) {
    return $purchase->items()->with('product')->get()->map(fn($item) => [
        'id' => $item->id,
        'product_id' => $item->product_id,
        'product_name' => $item->product->name,
        'quantity' => $item->quantity,
        'getting_rate' => $item->getting_rate,
        'mrp' => $item->mrp,
    ]);
})->name('api.purchases.items');

Route::get('sales/{sale}/items', function (App\Models\Sale $sale) {
    return $sale->items()->with('product')->get()->map(fn($item) => [
        'id' => $item->id,
        'product_id' => $item->product_id,
        'product_name' => $item->product->name,
        'quantity' => $item->quantity,
        'sale_rate' => $item->sale_rate,
        'mrp' => $item->mrp,
    ]);
})->name('api.sales.items');
