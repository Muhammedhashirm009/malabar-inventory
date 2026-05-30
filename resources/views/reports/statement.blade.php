@extends('layouts.app')
@section('title', 'Financial Statements')
@section('content')
<h2 class="mb-3">Financial Statements</h2>

<div class="card mb-3"><div class="card-body">
    <form method="GET" class="d-flex gap-2 align-items-center">
        <label class="form-label mb-0">Financial Year:</label>
        <select name="year" class="form-control" style="max-width:200px;">
            @foreach($years as $y)
                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>April {{ $y }} - March {{ $y + 1 }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary"><i data-lucide="file-text"></i> Generate</button>
    </form>
    <div class="mt-2 text-muted">Showing: {{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }} to {{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}</div>
</div></div>

<div class="card"><div class="card-body p-0">
    <table class="table">
        <thead><tr><th>Customer</th><th>Phone</th><th>Opening Balance</th><th>Total Sales</th><th>Total Returns</th><th>Total Payments</th><th>Closing Balance</th></tr></thead>
        <tbody>
        @php $totals = (object)['opening' => 0, 'sales' => 0, 'returns' => 0, 'payments' => 0, 'closing' => 0]; @endphp
        @forelse($customers as $c)
            @php
                $totals->opening += $c->opening_balance;
                $totals->sales += $c->total_sales;
                $totals->returns += $c->total_returns;
                $totals->payments += $c->total_payments;
                $totals->closing += $c->closing_balance;
            @endphp
            <tr>
                <td><strong>{{ $c->name }}</strong></td>
                <td>{{ $c->phone ?? '-' }}</td>
                <td>₹{{ number_format($c->opening_balance, 2) }}</td>
                <td>₹{{ number_format($c->total_sales, 2) }}</td>
                <td>₹{{ number_format($c->total_returns, 2) }}</td>
                <td class="text-success">₹{{ number_format($c->total_payments, 2) }}</td>
                <td class="{{ $c->closing_balance > 0 ? 'text-danger' : 'text-success' }}"><strong>₹{{ number_format($c->closing_balance, 2) }}</strong></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted p-4">No transactions for the selected year.</td></tr>
        @endforelse
        </tbody>
        @if($customers->count() > 0)
        <tfoot>
            <tr>
                <td colspan="2"><strong>TOTAL</strong></td>
                <td><strong>₹{{ number_format($totals->opening, 2) }}</strong></td>
                <td><strong>₹{{ number_format($totals->sales, 2) }}</strong></td>
                <td><strong>₹{{ number_format($totals->returns, 2) }}</strong></td>
                <td class="text-success"><strong>₹{{ number_format($totals->payments, 2) }}</strong></td>
                <td class="{{ $totals->closing > 0 ? 'text-danger' : 'text-success' }}"><strong>₹{{ number_format($totals->closing, 2) }}</strong></td>
            </tr>
        </tfoot>
        @endif
    </table>
</div></div>
@endsection
