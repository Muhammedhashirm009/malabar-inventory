@extends('layouts.app')
@section('title', 'Make Payment to Supplier')
@section('content')
<div class="mb-3"><a href="{{ route('supplier-payments.index') }}" class="btn btn-outline"><i data-lucide="arrow-left"></i> Back</a></div>
<div class="card" style="max-width:600px; overflow: visible;" x-data="paymentForm({{ $selectedSupplier ? json_encode(['id' => $selectedSupplier->id, 'name' => $selectedSupplier->name, 'phone' => $selectedSupplier->phone, 'current_balance' => (float)$selectedSupplier->current_balance]) : 'null' }})">
    <div class="card-header"><h3>Record Supplier Payment</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('supplier-payments.store') }}">
            @csrf
            <div class="form-group mb-3" style="position:relative;">
                <label class="form-label">Supplier *</label>
                <input type="text" class="form-control" placeholder="Search supplier by name or phone..." x-model="query" @input="search()" @focus="showDrop = results.length > 0" @click.outside="showDrop = false">
                <input type="hidden" name="supplier_id" :value="selected ? selected.id : ''">
                <div class="search-dropdown" x-show="showDrop" x-transition>
                    <template x-for="s in results" :key="s.id">
                        <div class="search-dropdown-item" @click="pick(s)">
                            <strong x-text="s.name"></strong> <span class="text-muted" x-text="s.phone || ''"></span>
                            <div class="text-muted" style="font-size:0.85rem;">Outstanding: ₹<span x-text="parseFloat(s.current_balance).toFixed(2)"></span></div>
                        </div>
                    </template>
                </div>
                <div x-show="selected" class="mt-2 p-2" style="background:var(--bg-hover); border-radius:8px;">
                    <strong x-text="selected?.name"></strong>
                    <div>We owe: <strong class="text-danger">₹<span x-text="selected ? parseFloat(selected.current_balance).toFixed(2) : '0.00'"></span></strong></div>
                </div>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">Amount (₹) *</label>
                <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                @error('amount')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group mb-3">
                <label class="form-label">Payment Date *</label>
                <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes (e.g. Bank Transfer, Cheque #)"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" :disabled="!selected"><i data-lucide="credit-card"></i> Record Payment</button>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
function paymentForm(initialSupplier = null) {
    return {
        query: initialSupplier ? initialSupplier.name : '', results: [], showDrop: false, selected: initialSupplier, timeout: null,
        search() {
            this.selected = null;
            clearTimeout(this.timeout);
            if (this.query.length < 2) { this.results = []; this.showDrop = false; return; }
            this.timeout = setTimeout(() => {
                fetch('/api/suppliers/search?q=' + encodeURIComponent(this.query))
                    .then(r => r.json()).then(d => { this.results = d; this.showDrop = true; });
            }, 300);
        },
        pick(s) { this.selected = s; this.query = s.name; this.showDrop = false; }
    }
}
</script>
@endpush
