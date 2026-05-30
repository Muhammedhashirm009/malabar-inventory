@extends('layouts.app')
@section('title', 'New Purchase')

@push('styles')
<style>
.badge-success {
    background: var(--success-bg);
    color: var(--success);
    padding: 0.25rem 0.6rem;
    border-radius: var(--radius-sm);
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border: 1px solid rgba(16, 185, 129, 0.2);
}
.badge-danger {
    background: var(--danger-bg);
    color: var(--danger);
    padding: 0.25rem 0.6rem;
    border-radius: var(--radius-sm);
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border: 1px solid rgba(239, 68, 68, 0.2);
}
.search-dropdown-item:hover {
    background: var(--bg-hover);
}
</style>
@endpush

@section('content')
<div class="mb-3"><a href="{{ route('purchases.index') }}" class="btn btn-outline"><i data-lucide="arrow-left"></i> Back</a></div>

<form method="POST" action="{{ route('purchases.store') }}" x-data="purchaseForm()" @submit.prevent="submitForm($el)">
    @csrf
    <div class="card mb-3" style="overflow: visible;">
        <div class="card-header"><h3>Purchase Details</h3></div>
        <div class="card-body">
            <div class="grid-3 gap-3">
                <div class="form-group" style="position:relative;">
                    <label class="form-label">Supplier *</label>
                    
                    <!-- Search Input (Hidden when selected) -->
                    <div x-show="!selectedSupplier" style="position: relative;">
                        <i data-lucide="search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted); pointer-events: none;"></i>
                        <input type="text" class="form-control" placeholder="Search supplier by name or phone..." 
                               x-model="supplierQuery" @input="searchSuppliers()" @focus="showSupplierDrop = supplierResults.length > 0" 
                               @click.outside="showSupplierDrop = false" style="padding-left: 38px;" :required="!selectedSupplier">
                        
                        <!-- Dropdown Search Results -->
                        <div class="search-dropdown" x-show="showSupplierDrop" x-transition>
                            <template x-for="s in supplierResults" :key="s.id">
                                <div class="search-dropdown-item" @click="pickSupplier(s)" style="cursor: pointer; padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong x-text="s.name" style="color: var(--text-primary); display: block;"></strong>
                                        <span class="text-muted" style="font-size: 0.85rem;" x-text="s.phone || 'No phone'"></span>
                                    </div>
                                    <div class="text-right">
                                        <span class="badge" :class="parseFloat(s.current_balance) > 0 ? 'badge-danger' : 'badge-success'" style="font-size: 0.8rem; font-weight: 500; padding: 3px 8px; border-radius: var(--radius-sm);">
                                            Balance: ₹<span x-text="parseFloat(s.current_balance).toFixed(2)"></span>
                                        </span>
                                    </div>
                                </div>
                            </template>
                            <div x-show="supplierResults.length === 0 && supplierQuery.length >= 2" class="search-dropdown-item text-muted" style="padding: 10px 12px;">No suppliers found</div>
                        </div>
                    </div>
                    
                    <!-- Hidden field to submit ID -->
                    <input type="hidden" name="supplier_id" :value="selectedSupplier ? selectedSupplier.id : ''">
                    
                    <!-- Selected Supplier Card -->
                    <div x-show="selectedSupplier" x-transition class="selected-supplier-card" style="display: flex; align-items: center; justify-content: space-between; background: var(--bg-hover); border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 1rem; box-shadow: var(--shadow-sm);">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--gradient-subtle); display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-light);">
                                <i data-lucide="factory" style="width: 20px; height: 20px; color: var(--accent);"></i>
                            </div>
                            <div>
                                <strong x-text="selectedSupplier?.name" style="font-size: 1.1rem; color: var(--text-primary); display: block; line-height: 1.2;"></strong>
                                <span class="text-muted" style="font-size: 0.85rem;" x-text="selectedSupplier?.phone ? 'Phone: ' + selectedSupplier.phone : 'No phone'"></span>
                                <div style="margin-top: 4px;">
                                    <span class="badge" :class="parseFloat(selectedSupplier?.current_balance) > 0 ? 'badge-danger' : 'badge-success'" style="font-size: 0.8rem; font-weight: 600; padding: 2px 8px;">
                                        Outstanding Balance: ₹<span x-text="selectedSupplier ? parseFloat(selectedSupplier.current_balance).toFixed(2) : '0.00'"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline" @click="clearSupplier()" style="border-color: rgba(239, 68, 68, 0.2); color: var(--danger); padding: 6px 12px; display: inline-flex; align-items: center; gap: 6px;">
                            <i data-lucide="x" style="width: 14px; height: 14px;"></i> Change
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Purchase Date *</label>
                    <input type="date" name="purchase_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="Optional notes">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3" style="overflow: visible;">
        <div class="card-header"><h3>Product Items</h3></div>
        <div class="card-body">
            <div class="form-group mb-3" style="position:relative; max-width: 500px;">
                <label class="form-label">Search Product</label>
                <input type="text" class="form-control" placeholder="Type product name or SKU..."
                       x-model="searchQuery" @input="searchProducts()" @focus="showDropdown = searchResults.length > 0"
                       @click.outside="showDropdown = false">
                <div class="search-dropdown" x-show="showDropdown" x-transition>
                    <template x-for="p in searchResults" :key="p.id">
                        <div class="search-dropdown-item" @click="addProduct(p)">
                            <div><strong x-text="p.name"></strong> <span class="text-muted" x-text="p.sku ? '(' + p.sku + ')' : ''"></span></div>
                            <div class="text-muted" style="font-size:0.85rem;">MRP: ₹<span x-text="parseFloat(p.mrp).toFixed(2)"></span> | Stock: <span x-text="p.stock"></span></div>
                        </div>
                    </template>
                    <div x-show="searchResults.length === 0 && searchQuery.length >= 2" class="search-dropdown-item text-muted">No products found</div>
                </div>
            </div>

            <div x-show="items.length > 0">
                <table class="table">
                    <thead><tr><th>#</th><th>Product</th><th>MRP</th><th>Getting Rate *</th><th>Sale Rate *</th><th>Qty *</th><th>Total</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="(item, idx) in items" :key="idx">
                            <tr>
                                <td x-text="idx + 1"></td>
                                <td><span x-text="item.name"></span><br><small class="text-muted" x-text="item.unit"></small>
                                    <input type="hidden" :name="'items['+idx+'][product_id]'" :value="item.product_id">
                                    <input type="hidden" :name="'items['+idx+'][mrp]'" :value="item.mrp">
                                </td>
                                <td>₹<span x-text="parseFloat(item.mrp).toFixed(2)"></span></td>
                                <td><input type="number" :name="'items['+idx+'][getting_rate]'" x-model="item.getting_rate" class="form-control" step="0.01" min="0" required style="width:120px;"></td>
                                <td><input type="number" :name="'items['+idx+'][sale_rate]'" x-model="item.sale_rate" class="form-control" step="0.01" min="0" required style="width:120px;"></td>
                                <td><input type="number" :name="'items['+idx+'][quantity]'" x-model="item.quantity" class="form-control" step="0.01" min="0.01" required style="width:100px;"></td>
                                <td><strong>₹<span x-text="itemTotal(item).toFixed(2)"></span></strong></td>
                                <td><button type="button" class="btn btn-sm btn-danger" @click="removeItem(idx)"><i data-lucide="x"></i></button></td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="6" class="text-right"><strong>Grand Total:</strong></td><td colspan="2"><strong class="text-success">₹<span x-text="grandTotal.toFixed(2)"></span></strong></td></tr>
                    </tfoot>
                </table>
            </div>
            <div x-show="items.length === 0" class="text-center text-muted p-4">Search and add products above</div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg" :disabled="items.length === 0"><i data-lucide="save"></i> Save Purchase</button>
</form>
@endsection

@push('scripts')
<script>
function purchaseForm() {
    return {
        items: [],
        searchQuery: '',
        searchResults: [],
        showDropdown: false,
        searchTimeout: null,
        
        // Supplier search variables
        supplierQuery: '',
        supplierResults: [],
        showSupplierDrop: false,
        selectedSupplier: null,
        supplierTimeout: null,
        
        searchSuppliers() {
            this.selectedSupplier = null;
            clearTimeout(this.supplierTimeout);
            if (this.supplierQuery.length < 2) { this.supplierResults = []; this.showSupplierDrop = false; return; }
            this.supplierTimeout = setTimeout(() => {
                fetch('/api/suppliers/search?q=' + encodeURIComponent(this.supplierQuery))
                    .then(r => r.json()).then(data => { this.supplierResults = data; this.showSupplierDrop = true; });
            }, 300);
        },
        init() {
            this.$nextTick(() => {
                if (window.lucide) window.lucide.createIcons();
            });
        },
        pickSupplier(s) {
            this.selectedSupplier = s;
            this.supplierQuery = s.name;
            this.showSupplierDrop = false;
            this.$nextTick(() => {
                if (window.lucide) window.lucide.createIcons();
            });
        },
        clearSupplier() {
            this.selectedSupplier = null;
            this.supplierQuery = '';
            this.supplierResults = [];
            this.showSupplierDrop = false;
        },
        
        searchProducts() {
            clearTimeout(this.searchTimeout);
            if (this.searchQuery.length < 2) { this.searchResults = []; this.showDropdown = false; return; }
            this.searchTimeout = setTimeout(() => {
                fetch('/api/products/search?q=' + encodeURIComponent(this.searchQuery))
                    .then(r => r.json()).then(data => { this.searchResults = data; this.showDropdown = true; });
            }, 300);
        },
        addProduct(p) {
            if (this.items.find(i => i.product_id === p.id)) { alert('Product already added'); return; }
            this.items.push({ product_id: p.id, name: p.name, sku: p.sku, unit: p.unit, mrp: p.mrp, getting_rate: p.getting_rate || '', sale_rate: p.sale_rate || '', quantity: 1 });
            this.searchQuery = ''; this.searchResults = []; this.showDropdown = false;
            this.$nextTick(() => lucide.createIcons());
        },
        removeItem(idx) { this.items.splice(idx, 1); },
        itemTotal(item) { return (parseFloat(item.getting_rate) || 0) * (parseFloat(item.quantity) || 0); },
        get grandTotal() { return this.items.reduce((s, i) => s + this.itemTotal(i), 0); },
        submitForm(el) {
            if (!this.selectedSupplier) { alert('Please select a supplier from the list'); return; }
            if (this.items.length === 0) { alert('Add at least one product'); return; }
            el.submit();
        }
    }
}
</script>
@endpush
