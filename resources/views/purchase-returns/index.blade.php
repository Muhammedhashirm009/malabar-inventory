@extends('layouts.app')
@section('title', 'Purchase Returns')
@section('content')
<div class="d-flex justify-between align-items-center mb-3">
    <h2>Purchase Returns</h2>
    <a href="{{ route('purchase-returns.create') }}" class="btn btn-primary"><i data-lucide="plus"></i> New Return</a>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table">
        <thead><tr><th>Return #</th><th>Purchase</th><th>Supplier</th><th>Date</th><th>Amount</th><th>Reason</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($returns as $r)
            <tr>
                <td><strong>{{ $r->return_number }}</strong></td>
                <td>{{ $r->purchase->invoice_number }}</td>
                <td>{{ $r->supplier->name ?? '-' }}</td>
                <td>{{ $r->return_date->format('d M Y') }}</td>
                <td>₹{{ number_format($r->total_amount, 2) }}</td>
                <td>{{ Str::limit($r->reason, 40) }}</td>
                <td>
                    <form action="{{ route('purchase-returns.destroy', $r) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this purchase return? This will reverse inventory and ledger changes.');" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger"><i data-lucide="trash-2"></i> Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted p-4">No purchase returns.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-2">{{ $returns->links() }}</div>
@endsection
