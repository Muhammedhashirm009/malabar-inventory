@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="card card-static mb-3" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3>Shop & Invoice Settings</h3>
    </div>
    <div class="card-body">
        <form action="{{ route('settings.update') }}" method="POST">
            @csrf
            
            <!-- Section 1: Shop Identity -->
            <div style="margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 16px;">
                <h4 style="margin-bottom: 14px; color: var(--accent-light); display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="store" style="width: 18px; height: 18px;"></i>
                    Shop Identity & Contact Info
                </h4>
                
                <div class="form-group">
                    <label for="shop_name">Shop Name *</label>
                    <input type="text" id="shop_name" name="shop_name" class="form-control" value="{{ old('shop_name', config('settings.shop_name')) }}" required>
                </div>
                
                <div class="form-group">
                    <label for="shop_address">Shop Address</label>
                    <textarea id="shop_address" name="shop_address" class="form-control" rows="3">{{ old('shop_address', config('settings.shop_address')) }}</textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="shop_phone">Phone Number</label>
                        <input type="text" id="shop_phone" name="shop_phone" class="form-control" value="{{ old('shop_phone', config('settings.shop_phone')) }}">
                    </div>
                    <div class="form-group">
                        <label for="shop_email">Email Address</label>
                        <input type="email" id="shop_email" name="shop_email" class="form-control" value="{{ old('shop_email', config('settings.shop_email')) }}">
                    </div>
                </div>
                
                <div class="form-group" style="max-width: 50%;">
                    <label for="shop_gstin">GSTIN (Business ID / Tax ID)</label>
                    <input type="text" id="shop_gstin" name="shop_gstin" class="form-control" value="{{ old('shop_gstin', config('settings.shop_gstin')) }}" placeholder="e.g. 32AAAAA1111A1Z1">
                </div>
            </div>

            <!-- Section 2: Invoice Configurations -->
            <div style="margin-bottom: 24px;">
                <h4 style="margin-bottom: 14px; color: var(--accent-light); display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="file-text" style="width: 18px; height: 18px;"></i>
                    Invoice Number Formats
                </h4>
                
                <div class="form-row" style="margin-bottom: 14px;">
                    <div class="form-group">
                        <label for="sale_invoice_prefix">Sales Invoice Prefix *</label>
                        <input type="text" id="sale_invoice_prefix" name="sale_invoice_prefix" class="form-control" value="{{ old('sale_invoice_prefix', config('settings.sale_invoice_prefix')) }}" required>
                        <small class="form-hint">E.g., SAL (generates SAL-YYYYMMDD-001)</small>
                    </div>
                    <div class="form-group">
                        <label for="sale_invoice_suffix">Sales Invoice Suffix</label>
                        <input type="text" id="sale_invoice_suffix" name="sale_invoice_suffix" class="form-control" value="{{ old('sale_invoice_suffix', config('settings.sale_invoice_suffix')) }}">
                        <small class="form-hint">Optional suffix at end (e.g., -LTD)</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="purchase_invoice_prefix">Purchases Invoice Prefix *</label>
                        <input type="text" id="purchase_invoice_prefix" name="purchase_invoice_prefix" class="form-control" value="{{ old('purchase_invoice_prefix', config('settings.purchase_invoice_prefix')) }}" required>
                        <small class="form-hint">E.g., PUR (generates PUR-YYYYMMDD-001)</small>
                    </div>
                    <div class="form-group">
                        <label for="purchase_invoice_suffix">Purchases Invoice Suffix</label>
                        <input type="text" id="purchase_invoice_suffix" name="purchase_invoice_suffix" class="form-control" value="{{ old('purchase_invoice_suffix', config('settings.purchase_invoice_suffix')) }}">
                        <small class="form-hint">Optional suffix at end (e.g., -LTD)</small>
                    </div>
                </div>
            </div>

            <!-- Action buttons -->
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i data-lucide="save"></i>
                    Save Settings
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
