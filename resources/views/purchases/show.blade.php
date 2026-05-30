@extends('layouts.app')
@section('title', 'Purchase ' . $purchase->invoice_number)
@section('content')
<div class="mb-3"><a href="{{ route('purchases.index') }}" class="btn btn-outline"><i data-lucide="arrow-left"></i> Back</a></div>
<div class="card mb-3">
    <div class="card-header"><h3>{{ $purchase->invoice_number }}</h3></div>
    <div class="card-body">
        <div class="grid-3 gap-3">
            <div><span class="text-muted">Supplier</span><br><strong>{{ $purchase->supplier->name }}</strong></div>
            <div><span class="text-muted">Date</span><br><strong>{{ $purchase->purchase_date->format('d M Y') }}</strong></div>
            <div><span class="text-muted">Total Amount</span><br><strong class="text-success">₹{{ number_format($purchase->total_amount, 2) }}</strong></div>
        </div>
        @if($purchase->notes)<div class="mt-2"><span class="text-muted">Notes:</span> {{ $purchase->notes }}</div>@endif
    </div>
</div>
<div class="card">
    <div class="card-header"><h3>Items</h3></div>
    <div class="card-body p-0">
        <table class="table">
            <thead><tr><th>#</th><th>Product</th><th>MRP</th><th>Getting Rate</th><th>Sale Rate</th><th>Qty</th><th>Total</th></tr></thead>
            <tbody>
            @foreach($purchase->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td><td>{{ $item->product->name }}</td>
                    <td>₹{{ number_format($item->mrp, 2) }}</td><td>₹{{ number_format($item->getting_rate, 2) }}</td>
                    <td>₹{{ number_format($item->sale_rate, 2) }}</td><td>{{ $item->quantity }}</td>
                    <td><strong>₹{{ number_format($item->total_price, 2) }}</strong></td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr><td colspan="6" class="text-right"><strong>Grand Total</strong></td><td><strong class="text-success">₹{{ number_format($purchase->total_amount, 2) }}</strong></td></tr></tfoot>
        </table>
    </div>
</div>
@endsection
