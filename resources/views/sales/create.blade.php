@extends('layouts.app')
@section('title', 'New Sale')
@section('content')
<div class="mb-3"><a href="{{ route('sales.index') }}" class="btn btn-outline"><i data-lucide="arrow-left"></i> Back</a></div>

<form method="POST" action="{{ route('sales.store') }}" x-data="saleForm()" @submit.prevent="submitForm($el)">
    @csrf
    <div class="card mb-3" style="overflow: visible;">
        <div class="card-header"><h3>Sale Details</h3></div>
        <div class="card-body">
            <div class="grid-3 gap-3">
                <div class="form-group" style="position:relative;">
                    <label class="form-label">Customer *</label>
                    <input type="text" class="form-control" placeholder="Search customer..." x-model="customerQuery" @input="searchCustomers()" @focus="showCustomerDropdown = customerResults.length > 0" @click.outside="showCustomerDropdown = false">
                    <input type="hidden" name="customer_id" :value="selectedCustomer ? selectedCustomer.id : ''">
                    <div class="search-dropdown" x-show="showCustomerDropdown" x-transition>
                        <template x-for="c in customerResults" :key="c.id">
                            <div class="search-dropdown-item" @click="selectCustomer(c)">
                                <strong x-text="c.name"></strong> <span class="text-muted" x-text="c.phone || ''"></span>
                                <div class="text-muted" style="font-size:0.85rem;">Balance: ₹<span x-text="parseFloat(c.current_balance).toFixed(2)"></span></div>
                            </div>
                        </template>
                    </div>
                    <div x-show="selectedCustomer" class="mt-1">
                        <span class="badge badge-primary" x-text="selectedCustomer?.name"></span>
                        <span class="text-muted ml-1">Outstanding: ₹<span x-text="selectedCustomer ? parseFloat(selectedCustomer.current_balance).toFixed(2) : '0.00'" :class="selectedCustomer && selectedCustomer.current_balance > 0 ? 'text-danger' : 'text-success'"></span></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Sale Date *</label>
                    <input type="date" name="sale_date" class="form-control" value="{{ date('Y-m-d') }}" required>
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
                <input type="text" class="form-control" placeholder="Type product name..."
                       x-model="searchQuery" @input="searchProducts()" @focus="showDropdown = searchResults.length > 0" @click.outside="showDropdown = false">
                <div class="search-dropdown" x-show="showDropdown" x-transition>
                    <template x-for="p in searchResults" :key="p.id">
                        <div class="search-dropdown-item" @click="addProduct(p)">
                            <div><strong x-text="p.name"></strong></div>
                            <div class="text-muted" style="font-size:0.85rem;">MRP: ₹<span x-text="parseFloat(p.mrp).toFixed(2)"></span> | Sale Rate: ₹<span x-text="parseFloat(p.sale_rate).toFixed(2)"></span> | Stock: <span x-text="p.stock" :class="p.stock < 5 ? 'text-danger' : ''"></span></div>
                        </div>
                    </template>
                    <div x-show="searchResults.length === 0 && searchQuery.length >= 2" class="search-dropdown-item text-muted">No products found</div>
                </div>
            </div>

            <div x-show="items.length > 0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>MRP</th>
                            <th>Sale Rate</th>
                            <th>Discount (₹)</th>
                            <th>Net Rate</th>
                            <th>Qty *</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, idx) in items" :key="idx">
                            <tr>
                                <td x-text="idx + 1"></td>
                                <td>
                                    <span x-text="item.name"></span><br>
                                    <small :class="parseFloat(item.stock) <= 0 ? 'text-danger font-semibold' : (parseFloat(item.stock) < 5 ? 'text-warning font-semibold' : 'text-muted')" x-text="'Stock: ' + item.stock + ' ' + item.unit"></small>
                                    <input type="hidden" :name="'items['+idx+'][product_id]'" :value="item.product_id">
                                    <input type="hidden" :name="'items['+idx+'][sale_rate]'" :value="getNetRate(item)">
                                    <input type="hidden" :name="'items['+idx+'][discount]'" :value="parseFloat(item.discount) || 0">
                                </td>
                                <td>₹<span x-text="parseFloat(item.mrp).toFixed(2)"></span></td>
                                <td>₹<span x-text="parseFloat(item.original_sale_rate).toFixed(2)"></span></td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 4px; width: 140px;">
                                        <input type="number" 
                                               x-model="item.discount" 
                                               @input="validateDiscount(item)"
                                               class="form-control" 
                                               step="0.01" 
                                               min="0" 
                                               :max="item.original_sale_rate" 
                                               placeholder="0.00"
                                               :class="item.error ? 'is-invalid' : ''">
                                        <div style="display: flex; justify-content: space-between; align-items: center; min-height: 18px;">
                                            <span class="discount-pct-badge" 
                                                  x-show="parseFloat(item.discount) > 0 && !item.error" 
                                                  x-text="getDiscountPct(item)">
                                            </span>
                                            <span class="badge badge-outline" 
                                                  x-show="item.lastDiscountInfo" 
                                                  x-text="item.lastDiscountInfo"
                                                  style="font-size:0.7rem; border: 1px solid var(--border-color); color:var(--text-muted); padding: 1px 4px; border-radius: 4px;">
                                            </span>
                                        </div>
                                        <span class="text-danger" 
                                              x-show="item.error" 
                                              x-text="item.error" 
                                              style="font-size:0.72rem; line-height: 1.1; margin-top:2px; display:block;">
                                        </span>
                                    </div>
                                </td>
                                <td>₹<span x-text="getNetRate(item).toFixed(2)"></span></td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 4px; width: 110px;">
                                        <input type="number" 
                                               :name="'items['+idx+'][quantity]'" 
                                               x-model="item.quantity" 
                                               @input="validateQuantity(item)"
                                               class="form-control" 
                                               step="0.01" 
                                               min="0.01" 
                                               required 
                                               :class="item.qtyError ? 'is-invalid' : ''">
                                        <span class="text-danger" 
                                              x-show="item.qtyError" 
                                              x-text="item.qtyError" 
                                              style="font-size:0.72rem; line-height: 1.1; margin-top:2px; display:block;">
                                        </span>
                                    </div>
                                </td>
                                <td><strong>₹<span x-text="itemTotal(item).toFixed(2)"></span></strong></td>
                                <td><button type="button" class="btn btn-sm btn-danger" @click="removeItem(idx)"><i data-lucide="x"></i></button></td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7" class="text-right"><strong>Grand Total:</strong></td>
                            <td colspan="2"><strong class="text-success">₹<span x-text="grandTotal.toFixed(2)"></span></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div x-show="items.length === 0" class="text-center text-muted p-4">Search and add products above</div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary btn-lg" :disabled="items.length === 0 || !selectedCustomer || hasErrors"><i data-lucide="save"></i> Save Sale</button>
</form>
@endsection

@push('scripts')
<script>
function saleForm() {
    return {
        items: [], searchQuery: '', searchResults: [], showDropdown: false, searchTimeout: null,
        selectedCustomer: null, customerQuery: '', customerResults: [], showCustomerDropdown: false, customerTimeout: null,
        searchProducts() {
            clearTimeout(this.searchTimeout);
            if (this.searchQuery.length < 2) { this.searchResults = []; this.showDropdown = false; return; }
            this.searchTimeout = setTimeout(() => {
                fetch('/api/products/search?q=' + encodeURIComponent(this.searchQuery))
                    .then(r => r.json()).then(data => { this.searchResults = data; this.showDropdown = true; });
            }, 300);
        },
        searchCustomers() {
            clearTimeout(this.customerTimeout);
            this.selectedCustomer = null;
            if (this.customerQuery.length < 2) { this.customerResults = []; this.showCustomerDropdown = false; return; }
            this.customerTimeout = setTimeout(() => {
                fetch('/api/customers/search?q=' + encodeURIComponent(this.customerQuery))
                    .then(r => r.json()).then(data => { this.customerResults = data; this.showCustomerDropdown = true; });
            }, 300);
        },
        selectCustomer(c) {
            this.selectedCustomer = c;
            this.customerQuery = c.name;
            this.showCustomerDropdown = false;
            // Fetch last discount for all products already added
            this.items.forEach(item => {
                this.fetchLastDiscount(item);
            });
        },
        addProduct(p) {
            if (this.items.find(i => i.product_id === p.id)) { alert('Already added'); return; }
            if (p.stock <= 0) { alert('No stock available!'); return; }
            const newItem = { 
                product_id: p.id, 
                name: p.name, 
                unit: p.unit, 
                mrp: p.mrp, 
                original_sale_rate: p.sale_rate, 
                getting_rate: p.getting_rate,
                discount: 0, 
                stock: p.stock, 
                quantity: 1,
                error: null,
                qtyError: null,
                lastDiscountInfo: null
            };
            this.items.push(newItem);
            this.validateDiscount(newItem);
            this.validateQuantity(newItem);
            this.fetchLastDiscount(newItem);
            this.searchQuery = ''; this.searchResults = []; this.showDropdown = false;
            this.$nextTick(() => lucide.createIcons());
        },
        removeItem(idx) { this.items.splice(idx, 1); },
        fetchLastDiscount(item) {
            if (!this.selectedCustomer) return;
            fetch(`/api/customers/${this.selectedCustomer.id}/last-discount?product_id=${item.product_id}`)
                .then(r => r.json())
                .then(data => {
                    const reactiveItem = this.items.find(i => i.product_id === item.product_id);
                    if (reactiveItem) {
                        if (data.discount > 0) {
                            reactiveItem.discount = data.discount;
                            reactiveItem.lastDiscountInfo = `Last: ₹${data.discount}`;
                        } else {
                            reactiveItem.lastDiscountInfo = null;
                        }
                        this.validateDiscount(reactiveItem);
                    }
                });
        },
        getNetRate(item) {
            const original = parseFloat(item.original_sale_rate) || 0;
            const discount = parseFloat(item.discount) || 0;
            return Math.max(0, original - discount);
        },
        getDiscountPct(item) {
            const original = parseFloat(item.original_sale_rate) || 0;
            const discount = parseFloat(item.discount) || 0;
            if (original <= 0 || discount <= 0) return '';
            const pct = (discount / original) * 100;
            return pct.toFixed(1) + '% off';
        },
        validateDiscount(item) {
            const original = parseFloat(item.original_sale_rate) || 0;
            let discount = parseFloat(item.discount) || 0;
            const getting = parseFloat(item.getting_rate) || 0;
            
            if (discount < 0) {
                item.discount = 0;
                discount = 0;
            } else if (discount > original) {
                item.discount = original;
                discount = original;
            }
            
            const netRate = original - discount;
            if (netRate <= getting) {
                item.error = `Rate (₹${netRate.toFixed(2)}) cannot be <= Cost (₹${getting.toFixed(2)})!`;
            } else {
                item.error = null;
            }
        },
        validateQuantity(item) {
            const qty = parseFloat(item.quantity) || 0;
            const stock = parseFloat(item.stock) || 0;
            if (qty <= 0) {
                item.qtyError = 'Must be > 0';
            } else if (qty > stock) {
                item.qtyError = `Max: ${stock}`;
            } else {
                item.qtyError = null;
            }
        },
        itemTotal(item) { 
            return this.getNetRate(item) * (parseFloat(item.quantity) || 0); 
        },
        get grandTotal() { 
            return this.items.reduce((s, i) => s + this.itemTotal(i), 0); 
        },
        get hasErrors() {
            return this.items.some(item => item.error || item.qtyError);
        },
        submitForm(el) {
            if (!this.selectedCustomer) { alert('Select a customer'); return; }
            if (this.items.length === 0) { alert('Add at least one product'); return; }
            if (this.hasErrors) { alert('Please resolve all item errors first!'); return; }
            el.submit();
        }
    }
}
</script>
@endpush
