@extends('layouts.app')
@section('title', 'Supplier Ledger - ' . $supplier->name)
@section('content')
<div class="d-flex justify-between align-items-center mb-3">
    <a href="{{ route('suppliers.index') }}" class="btn btn-outline"><i data-lucide="arrow-left"></i> Back</a>
    <a href="{{ route('supplier-payments.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-primary"><i data-lucide="credit-card"></i> Make Payment</a>
</div>
<div class="card mb-3">
    <div class="card-body">
        <div class="grid-3 gap-3">
            <div><span class="text-muted">Supplier</span><br><strong>{{ $supplier->name }}</strong></div>
            <div><span class="text-muted">Phone</span><br><strong>{{ $supplier->phone ?? '-' }}</strong></div>
            <div><span class="text-muted">Outstanding Balance</span><br><strong class="{{ $supplier->current_balance > 0 ? 'text-danger' : 'text-success' }}">₹{{ number_format($supplier->current_balance, 2) }}</strong></div>
        </div>
    </div>
</div>
<div class="card mb-3"><div class="card-body">
    <form method="GET" class="d-flex gap-2 align-items-center">
        <label class="form-label mb-0">From:</label>
        <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}" style="max-width:180px;">
        <label class="form-label mb-0">To:</label>
        <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}" style="max-width:180px;">
        <button type="submit" class="btn btn-secondary">Filter</button>
        @if(request('from_date'))<a href="{{ route('suppliers.ledger', $supplier) }}" class="btn btn-outline">Clear</a>@endif
    </form>
</div></div>
<div class="card"><div class="card-body p-0">
    <table class="table">
        <thead><tr><th>Date</th><th>Description</th><th>Debit (We Owe More)</th><th>Credit (Paid/Returned)</th><th>Outstanding Balance</th></tr></thead>
        <tbody>
        @forelse($ledger as $entry)
            <tr>
                <td>{{ $entry->transaction_date->format('d M Y') }}</td>
                <td>{{ $entry->description }}</td>
                <td class="text-danger">{{ $entry->type === 'debit' ? '₹' . number_format($entry->amount, 2) : '' }}</td>
                <td class="text-success">{{ $entry->type === 'credit' ? '₹' . number_format($entry->amount, 2) : '' }}</td>
                <td><strong>₹{{ number_format($entry->running_balance, 2) }}</strong></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted p-4">No transactions found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
@endsection
