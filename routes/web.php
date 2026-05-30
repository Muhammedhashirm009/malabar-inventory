<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\MarginReportController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\SupplierPaymentController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::resource('products', ProductController::class)->except(['show']);
Route::resource('suppliers', SupplierController::class)->except(['show']);
Route::get('suppliers/{supplier}/ledger', [SupplierController::class, 'ledger'])->name('suppliers.ledger');
Route::resource('customers', CustomerController::class)->except(['show']);
Route::get('customers/{customer}/ledger', [CustomerController::class, 'ledger'])->name('customers.ledger');

Route::resource('purchases', PurchaseController::class);
Route::resource('sales', SaleController::class);
Route::get('sales/{sale}/print', [SaleController::class, 'print'])->name('sales.print');

Route::resource('purchase-returns', PurchaseReturnController::class)->only(['index', 'create', 'store', 'destroy']);
Route::resource('sale-returns', SaleReturnController::class)->only(['index', 'create', 'store', 'destroy']);

Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');

Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');

Route::get('supplier-payments', [SupplierPaymentController::class, 'index'])->name('supplier-payments.index');
Route::get('supplier-payments/create', [SupplierPaymentController::class, 'create'])->name('supplier-payments.create');
Route::post('supplier-payments', [SupplierPaymentController::class, 'store'])->name('supplier-payments.store');

Route::get('reports/margin', [MarginReportController::class, 'index'])->name('reports.margin');
Route::get('reports/statements', [StatementController::class, 'index'])->name('reports.statements');

Route::get('settings', [App\Http\Controllers\SettingController::class, 'index'])->name('settings.index');
Route::post('settings', [App\Http\Controllers\SettingController::class, 'update'])->name('settings.update');
