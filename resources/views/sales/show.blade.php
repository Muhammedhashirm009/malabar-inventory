@extends('layouts.app')
@section('title', 'Sale ' . $sale->invoice_number)
@section('content')
<div class="mb-3" style="display: flex; gap: 8px;">
    <a href="{{ route('sales.index') }}" class="btn btn-outline"><i data-lucide="arrow-left"></i> Back</a>
    <a href="{{ route('sales.print', $sale) }}" target="_blank" class="btn btn-primary">
        <i data-lucide="printer"></i> Print Invoice
    </a>
</div>
<div class="card mb-3">
    <div class="card-header"><h3>{{ $sale->invoice_number }}</h3></div>
    <div class="card-body">
        <div class="grid-3 gap-3">
            <div><span class="text-muted">Customer</span><br><strong>{{ $sale->customer->name }}</strong></div>
            <div><span class="text-muted">Date</span><br><strong>{{ $sale->sale_date->format('d M Y') }}</strong></div>
            <div><span class="text-muted">Total</span><br><strong class="text-success">₹{{ number_format($sale->total_amount, 2) }}</strong></div>
        </div>
        @if($sale->notes)<div class="mt-2"><span class="text-muted">Notes:</span> {{ $sale->notes }}</div>@endif
    </div>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>MRP</th>
                <th>Original Rate</th>
                <th>Discount</th>
                <th>Net Sale Rate</th>
                <th>Qty</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
        @foreach($sale->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->product->name }}</td>
                <td>₹{{ number_format($item->mrp, 2) }}</td>
                <td>₹{{ number_format($item->sale_rate + ($item->discount ?? 0), 2) }}</td>
                <td>
                    @if(($item->discount ?? 0) > 0)
                        <span class="text-danger">-₹{{ number_format($item->discount, 2) }}</span>
                        @php
                            $orig = $item->sale_rate + $item->discount;
                            $pct = $orig > 0 ? ($item->discount / $orig) * 100 : 0;
                        @endphp
                        <small class="text-muted">({{ number_format($pct, 1) }}%)</small>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td>₹{{ number_format($item->sale_rate, 2) }}</td>
                <td>{{ $item->quantity }}</td>
                <td><strong>₹{{ number_format($item->total_price, 2) }}</strong></td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7" class="text-right"><strong>Grand Total</strong></td>
                <td><strong class="text-success">₹{{ number_format($sale->total_amount, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>
</div></div>
@endsection
