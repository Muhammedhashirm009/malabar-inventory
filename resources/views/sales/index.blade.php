@extends('layouts.app')
@section('title', 'Sales')
@section('content')
<div class="d-flex justify-between align-items-center mb-3">
    <h2>Sales</h2>
    <a href="{{ route('sales.create') }}" class="btn btn-primary"><i data-lucide="plus"></i> New Sale</a>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table">
        <thead><tr><th>Invoice #</th><th>Customer</th><th>Date</th><th>Total Amount</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($sales as $s)
            <tr>
                <td><strong>{{ $s->invoice_number }}</strong></td>
                <td>{{ $s->customer->name ?? '-' }}</td>
                <td>{{ $s->sale_date->format('d M Y') }}</td>
                <td>₹{{ number_format($s->total_amount, 2) }}</td>
                <td style="display: flex; gap: 8px;">
                    <a href="{{ route('sales.show', $s) }}" class="btn btn-sm btn-outline"><i data-lucide="eye"></i> View</a>
                    <a href="{{ route('sales.edit', $s) }}" class="btn btn-sm btn-warning"><i data-lucide="edit"></i> Edit</a>
                    <form action="{{ route('sales.destroy', $s) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this sale invoice? This will reverse stock and ledger changes.');" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger"><i data-lucide="trash-2"></i> Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted p-4">No sales yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-2">{{ $sales->links() }}</div>
@endsection
