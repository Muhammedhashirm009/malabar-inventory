@extends('layouts.app')
@section('title', 'Margin Report')
@section('content')
<h2 class="mb-3">Margin Report</h2>

<div class="card mb-3"><div class="card-body">
    <form method="GET" class="d-flex gap-2 align-items-center">
        <label class="form-label mb-0">From:</label>
        <input type="date" name="from_date" class="form-control" value="{{ $fromDate }}" style="max-width:180px;">
        <label class="form-label mb-0">To:</label>
        <input type="date" name="to_date" class="form-control" value="{{ $toDate }}" style="max-width:180px;">
        <button type="submit" class="btn btn-primary"><i data-lucide="bar-chart-2"></i> Generate</button>
    </form>
</div></div>

<div class="grid-4 mb-3">
    <div class="stat-card">
        <div class="stat-card-icon" style="background: rgba(16,185,129,0.15); color: #10b981;"><i data-lucide="trending-up"></i></div>
        <div class="stat-card-info"><span class="stat-card-value">₹{{ number_format($totalRevenue, 2) }}</span><span class="stat-card-label">Total Revenue</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background: rgba(245,158,11,0.15); color: #f59e0b;"><i data-lucide="shopping-bag"></i></div>
        <div class="stat-card-info"><span class="stat-card-value">₹{{ number_format($totalCost, 2) }}</span><span class="stat-card-label">Total Cost</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background: rgba(99,102,241,0.15); color: #6366f1;"><i data-lucide="wallet"></i></div>
        <div class="stat-card-info"><span class="stat-card-value {{ $totalProfit >= 0 ? 'text-success' : 'text-danger' }}">₹{{ number_format($totalProfit, 2) }}</span><span class="stat-card-label">Total Profit</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background: rgba(59,130,246,0.15); color: #3b82f6;"><i data-lucide="percent"></i></div>
        <div class="stat-card-info"><span class="stat-card-value">{{ number_format($overallMargin, 1) }}%</span><span class="stat-card-label">Overall Margin</span></div>
    </div>
</div>

<div class="card"><div class="card-body p-0">
    <table class="table">
        <thead><tr><th>Product</th><th>Qty Sold</th><th>Avg Sale Rate</th><th>Avg Getting Rate</th><th>Revenue</th><th>Cost</th><th>Profit</th><th>Margin %</th></tr></thead>
        <tbody>
        @forelse($items as $item)
            <tr>
                <td><strong>{{ $item->product_name }}</strong></td>
                <td>{{ number_format($item->qty_sold, 2) }} {{ $item->unit }}</td>
                <td>₹{{ number_format($item->avg_sale_rate, 2) }}</td>
                <td>₹{{ number_format($item->avg_getting_rate, 2) }}</td>
                <td>₹{{ number_format($item->revenue, 2) }}</td>
                <td>₹{{ number_format($item->cost, 2) }}</td>
                <td class="{{ $item->profit >= 0 ? 'text-success' : 'text-danger' }}"><strong>₹{{ number_format($item->profit, 2) }}</strong></td>
                <td>
                    <span class="badge {{ $item->margin_pct >= 20 ? 'badge-success' : ($item->margin_pct >= 10 ? 'badge-warning' : 'badge-danger') }}">
                        {{ number_format($item->margin_pct, 1) }}%
                    </span>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted p-4">No sales found for the selected date range.</td></tr>
        @endforelse
        </tbody>
        @if($items->count() > 0)
        <tfoot>
            <tr>
                <td><strong>TOTAL</strong></td>
                <td></td><td></td><td></td>
                <td><strong>₹{{ number_format($totalRevenue, 2) }}</strong></td>
                <td><strong>₹{{ number_format($totalCost, 2) }}</strong></td>
                <td class="text-success"><strong>₹{{ number_format($totalProfit, 2) }}</strong></td>
                <td><strong>{{ number_format($overallMargin, 1) }}%</strong></td>
            </tr>
        </tfoot>
        @endif
    </table>
</div></div>
@endsection
