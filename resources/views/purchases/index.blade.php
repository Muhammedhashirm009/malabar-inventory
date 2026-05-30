@extends('layouts.app')
@section('title', 'Purchases')
@section('content')
<div class="d-flex justify-between align-items-center mb-3">
    <h2>Purchases</h2>
    <a href="{{ route('purchases.create') }}" class="btn btn-primary"><i data-lucide="plus"></i> New Purchase</a>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table">
        <thead><tr><th>Invoice #</th><th>Supplier</th><th>Date</th><th>Total Amount</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($purchases as $p)
            <tr>
                <td><strong>{{ $p->invoice_number }}</strong></td>
                <td>{{ $p->supplier->name ?? '-' }}</td>
                <td>{{ $p->purchase_date->format('d M Y') }}</td>
                <td>₹{{ number_format($p->total_amount, 2) }}</td>
                <td style="display: flex; gap: 8px;">
                    <a href="{{ route('purchases.show', $p) }}" class="btn btn-sm btn-outline"><i data-lucide="eye"></i> View</a>
                    <a href="{{ route('purchases.edit', $p) }}" class="btn btn-sm btn-warning"><i data-lucide="edit"></i> Edit</a>
                    <form action="{{ route('purchases.destroy', $p) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this purchase invoice? This will reverse stock and ledger changes.');" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger"><i data-lucide="trash-2"></i> Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted p-4">No purchases yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-2">{{ $purchases->links() }}</div>
@endsection
