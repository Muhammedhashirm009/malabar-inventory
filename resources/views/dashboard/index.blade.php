@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="grid-4 mb-3">
    <div class="stat-card">
        <div class="stat-card-icon" style="background: rgba(59,130,246,0.15); color: #3b82f6;"><i data-lucide="package"></i></div>
        <div class="stat-card-info"><span class="stat-card-value">{{ $totalProducts }}</span><span class="stat-card-label">Total Products</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background: rgba(16,185,129,0.15); color: #10b981;"><i data-lucide="trending-up"></i></div>
        <div class="stat-card-info"><span class="stat-card-value">₹{{ number_format($todaySalesAmount, 2) }}</span><span class="stat-card-label">Today's Sales ({{ $todaySalesCount }})</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background: rgba(245,158,11,0.15); color: #f59e0b;"><i data-lucide="shopping-cart"></i></div>
        <div class="stat-card-info"><span class="stat-card-value">₹{{ number_format($todayPurchasesAmount, 2) }}</span><span class="stat-card-label">Today's Purchases ({{ $todayPurchasesCount }})</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background: rgba(239,68,68,0.15); color: #ef4444;"><i data-lucide="alert-triangle"></i></div>
        <div class="stat-card-info"><span class="stat-card-value">₹{{ number_format($totalOutstanding, 2) }}</span><span class="stat-card-label">Total Outstanding</span></div>
    </div>
</div>

<div class="grid-3 mb-3">
    <div class="stat-card">
        <div class="stat-card-icon" style="background: rgba(99,102,241,0.15); color: #6366f1;"><i data-lucide="calendar"></i></div>
        <div class="stat-card-info"><span class="stat-card-value">₹{{ number_format($monthSales, 2) }}</span><span class="stat-card-label">This Month Sales</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background: rgba(245,158,11,0.15); color: #f59e0b;"><i data-lucide="truck"></i></div>
        <div class="stat-card-info"><span class="stat-card-value">₹{{ number_format($monthPurchases, 2) }}</span><span class="stat-card-label">This Month Purchases</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background: rgba(16,185,129,0.15); color: {{ $todayProfit >= 0 ? '#10b981' : '#ef4444' }};"><i data-lucide="wallet"></i></div>
        <div class="stat-card-info"><span class="stat-card-value {{ $todayProfit >= 0 ? 'text-success' : 'text-danger' }}">₹{{ number_format($todayProfit, 2) }}</span><span class="stat-card-label">Today's Profit</span></div>
    </div>
</div>

<div class="grid-2 mb-3">
    <div class="card">
        <div class="card-header"><h3>Sales vs Purchases Trend</h3></div>
        <div class="card-body"><canvas id="salesChart" height="250"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header"><h3>Top Customers by Outstanding</h3></div>
        <div class="card-body"><canvas id="customersChart" height="250"></canvas></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Recent Sales</h3></div>
        <div class="card-body p-0">
            <table class="table">
                <thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th>Amount</th></tr></thead>
                <tbody>
                @forelse($recentSales as $sale)
                    <tr>
                        <td><a href="{{ route('sales.show', $sale) }}">{{ $sale->invoice_number }}</a></td>
                        <td>{{ $sale->customer->name ?? '-' }}</td>
                        <td>{{ $sale->sale_date->format('d M Y') }}</td>
                        <td>₹{{ number_format($sale->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">No sales yet</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3>Low Stock Alerts</h3></div>
        <div class="card-body p-0">
            <table class="table">
                <thead><tr><th>Product</th><th>Stock</th><th>Unit</th></tr></thead>
                <tbody>
                @forelse($lowStock as $inv)
                    <tr class="{{ $inv->quantity < 5 ? 'text-danger' : 'text-warning' }}">
                        <td>{{ $inv->product->name }}</td>
                        <td><strong>{{ $inv->quantity }}</strong></td>
                        <td>{{ $inv->product->unit }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">All stock levels OK</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartDefaults = { color: '#94a3b8', grid: { color: '#2d3140' } };

    new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: {
            labels: @json($monthLabels),
            datasets: [
                { label: 'Sales', data: @json($monthlySalesData), backgroundColor: 'rgba(16, 185, 129, 0.7)', borderColor: '#10b981', borderWidth: 1, borderRadius: 6 },
                { label: 'Purchases', data: @json($monthlyPurchasesData), backgroundColor: 'rgba(245, 158, 11, 0.7)', borderColor: '#f59e0b', borderWidth: 1, borderRadius: 6 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#94a3b8' } } },
            scales: {
                x: { ticks: { color: '#64748b' }, grid: { color: '#2d3140' } },
                y: { ticks: { color: '#64748b', callback: v => '₹' + v.toLocaleString() }, grid: { color: '#2d3140' } }
            }
        }
    });

    const topNames = @json($topCustomers->pluck('name'));
    const topBalances = @json($topCustomers->pluck('current_balance'));
    new Chart(document.getElementById('customersChart'), {
        type: 'bar',
        data: {
            labels: topNames,
            datasets: [{ label: 'Outstanding', data: topBalances, backgroundColor: 'rgba(239, 68, 68, 0.7)', borderColor: '#ef4444', borderWidth: 1, borderRadius: 6 }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: '#64748b', callback: v => '₹' + v.toLocaleString() }, grid: { color: '#2d3140' } },
                y: { ticks: { color: '#94a3b8' }, grid: { display: false } }
            }
        }
    });
});
</script>
@endpush
