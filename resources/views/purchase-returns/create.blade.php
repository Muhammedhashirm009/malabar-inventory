@extends('layouts.app')
@section('title', 'New Purchase Return')
@section('content')
<div class="mb-3"><a href="{{ route('purchase-returns.index') }}" class="btn btn-outline"><i data-lucide="arrow-left"></i> Back</a></div>
<form method="POST" action="{{ route('purchase-returns.store') }}" x-data="returnForm()" @submit.prevent="submitForm($el)">
    @csrf
    <div class="card mb-3">
        <div class="card-header"><h3>Purchase Return</h3></div>
        <div class="card-body">
            <div class="grid-3 gap-3">
                <div class="form-group">
                    <label class="form-label">Select Purchase *</label>
                    <select name="purchase_id" class="form-control" @change="loadItems($event.target.value)" required>
                        <option value="">Select Purchase</option>
                        @foreach($purchases as $p)
                            <option value="{{ $p->id }}" {{ ($selectedPurchase && $selectedPurchase->id == $p->id) ? 'selected' : '' }}>{{ $p->invoice_number }} - {{ $p->supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Return Date *</label>
                    <input type="date" name="return_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Reason</label>
                    <input type="text" name="reason" class="form-control" placeholder="Reason for return">
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-3" x-show="items.length > 0">
        <div class="card-header"><h3>Items to Return</h3></div>
        <div class="card-body p-0">
            <table class="table">
                <thead><tr><th>Product</th><th>Original Qty</th><th>Return Qty</th><th>Getting Rate</th><th>Total</th></tr></thead>
                <tbody>
                    <template x-for="(item, idx) in items" :key="idx">
                        <tr>
                            <td>
                                <span x-text="item.product_name"></span>
                                <input type="hidden" :name="'items['+idx+'][product_id]'" :value="item.product_id">
                                <input type="hidden" :name="'items['+idx+'][getting_rate]'" :value="item.getting_rate">
                            </td>
                            <td x-text="item.original_qty"></td>
                            <td><input type="number" :name="'items['+idx+'][quantity]'" x-model="item.return_qty" class="form-control" step="0.01" min="0" :max="item.original_qty" style="width:100px;"></td>
                            <td>₹<span x-text="parseFloat(item.getting_rate).toFixed(2)"></span></td>
                            <td>₹<span x-text="(parseFloat(item.return_qty||0) * parseFloat(item.getting_rate)).toFixed(2)"></span></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
    <button type="submit" class="btn btn-primary" x-show="items.length > 0"><i data-lucide="undo-2"></i> Process Return</button>
</form>
@endsection
@push('scripts')
@php
    $initialItems = $selectedPurchase
        ? $selectedPurchase->items->map(function($i) {
            return [
                'product_id' => $i->product_id,
                'product_name' => $i->product->name,
                'original_qty' => $i->quantity,
                'getting_rate' => $i->getting_rate,
                'return_qty' => 0,
            ];
        })->values()
        : [];
@endphp
<script>
function returnForm() {
    return {
        items: @json($initialItems),
        loadItems(purchaseId) {
            if (!purchaseId) { this.items = []; return; }
            fetch('/api/purchases/' + purchaseId + '/items').then(r => r.json()).then(data => {
                this.items = data.map(i => ({ product_id: i.product_id, product_name: i.product_name, original_qty: i.quantity, getting_rate: i.getting_rate, return_qty: 0 }));
            });
        },
        submitForm(el) {
            const hasItems = this.items.some(i => parseFloat(i.return_qty) > 0);
            if (!hasItems) { alert('Enter return quantity for at least one item'); return; }
            // Filter out items with 0 return qty before submit
            this.items = this.items.filter(i => parseFloat(i.return_qty) > 0);
            this.$nextTick(() => el.submit());
        }
    }
}
</script>
@endpush
