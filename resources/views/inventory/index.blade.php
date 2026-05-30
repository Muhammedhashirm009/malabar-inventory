@extends('layouts.app')
@section('title', 'Inventory')
@section('content')
<div class="d-flex justify-between align-items-center mb-3">
    <h2>Stock Overview</h2>
    <div class="stat-card" style="display:inline-flex; padding: 12px 20px;">
        <span class="text-muted mr-2">Total Stock Value:</span>
        <strong class="text-success">₹{{ number_format($totalValue, 2) }}</strong>
    </div>
</div>
<div class="card mb-3"><div class="card-body">
    <form method="GET" class="d-flex gap-2">
        <input type="text" name="search" class="form-control" placeholder="Search product..." value="{{ request('search') }}" style="max-width:300px;">
        <button type="submit" class="btn btn-secondary">Search</button>
        @if(request('search'))<a href="{{ route('inventory.index') }}" class="btn btn-outline">Clear</a>@endif
    </form>
</div></div>
<div class="card"><div class="card-body p-0">
    <table class="table">
        <thead><tr><th>#</th><th>Product</th><th>Unit</th><th>MRP</th><th>Getting Rate</th><th>Sale Rate</th><th>Stock Qty</th><th>Stock Value</th></tr></thead>
        <tbody>
        @forelse($inventory as $i => $inv)
            <tr class="{{ $inv->quantity < 5 ? 'text-danger' : ($inv->quantity < 10 ? 'text-warning' : '') }}">
                <td>{{ $inventory->firstItem() + $i }}</td>
                <td><strong>{{ $inv->product->name ?? 'N/A' }}</strong></td>
                <td>{{ $inv->product->unit ?? '-' }}</td>
                <td>₹{{ number_format($inv->mrp, 2) }}</td>
                <td>₹{{ number_format($inv->getting_rate, 2) }}</td>
                <td>₹{{ number_format($inv->sale_rate, 2) }}</td>
                <td><strong>{{ number_format($inv->quantity, 2) }}</strong> @if($inv->quantity < 10)<span class="badge badge-warning">Low</span>@endif</td>
                <td>₹{{ number_format($inv->getting_rate * $inv->quantity, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted p-4">No inventory data. Make a purchase first.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-2">{{ $inventory->links() }}</div>
@endsection
