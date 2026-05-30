@extends('layouts.app')
@section('title', 'New Sale Return')
@section('content')
<div class="mb-3"><a href="{{ route('sale-returns.index') }}" class="btn btn-outline"><i data-lucide="arrow-left"></i> Back</a></div>
<form method="POST" action="{{ route('sale-returns.store') }}" x-data="returnForm()" @submit.prevent="submitForm($el)">
    @csrf
    <div class="card mb-3">
        <div class="card-header"><h3>Sale Return</h3></div>
        <div class="card-body">
            <div class="grid-3 gap-3">
                <div class="form-group">
                    <label class="form-label">Select Sale *</label>
                    <select name="sale_id" class="form-control" @change="loadItems($event.target.value)" required>
                        <option value="">Select Sale</option>
                        @foreach($sales as $s)
                            <option value="{{ $s->id }}" {{ ($selectedSale && $selectedSale->id == $s->id) ? 'selected' : '' }}>{{ $s->invoice_number }} - {{ $s->customer->name }}</option>
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
                <thead><tr><th>Product</th><th>Original Qty</th><th>Return Qty</th><th>Sale Rate</th><th>Total</th></tr></thead>
                <tbody>
                    <template x-for="(item, idx) in items" :key="idx">
                        <tr>
                            <td>
                                <span x-text="item.product_name"></span>
                                <input type="hidden" :name="'items['+idx+'][product_id]'" :value="item.product_id">
                                <input type="hidden" :name="'items['+idx+'][sale_rate]'" :value="item.sale_rate">
                            </td>
                            <td x-text="item.original_qty"></td>
                            <td><input type="number" :name="'items['+idx+'][quantity]'" x-model="item.return_qty" class="form-control" step="0.01" min="0" :max="item.original_qty" style="width:100px;"></td>
                            <td>₹<span x-text="parseFloat(item.sale_rate).toFixed(2)"></span></td>
                            <td>₹<span x-text="(parseFloat(item.return_qty||0) * parseFloat(item.sale_rate)).toFixed(2)"></span></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
    <button type="submit" class="btn btn-primary" x-show="items.length > 0"><i data-lucide="rotate-ccw"></i> Process Return</button>
</form>
@endsection
@push('scripts')
@php
    $initialItems = $selectedSale
        ? $selectedSale->items->map(function($i) {
            return [
                'product_id' => $i->product_id,
                'product_name' => $i->product->name,
                'original_qty' => $i->quantity,
                'sale_rate' => $i->sale_rate,
                'return_qty' => 0,
            ];
        })->values()
        : [];
@endphp
<script>
function returnForm() {
    return {
        items: @json($initialItems),
        loadItems(saleId) {
            if (!saleId) { this.items = []; return; }
            fetch('/api/sales/' + saleId + '/items').then(r => r.json()).then(data => {
                this.items = data.map(i => ({ product_id: i.product_id, product_name: i.product_name, original_qty: i.quantity, sale_rate: i.sale_rate, return_qty: 0 }));
            });
        },
        submitForm(el) {
            const hasItems = this.items.some(i => parseFloat(i.return_qty) > 0);
            if (!hasItems) { alert('Enter return quantity for at least one item'); return; }
            this.items = this.items.filter(i => parseFloat(i.return_qty) > 0);
            this.$nextTick(() => el.submit());
        }
    }
}
</script>
@endpush
