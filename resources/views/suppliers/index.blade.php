@extends('layouts.app')
@section('title', 'Suppliers')
@section('content')
<div class="d-flex justify-between align-items-center mb-3">
    <h2>Supplier Master</h2>
    <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i data-lucide="plus"></i> Add Supplier</a>
</div>
<div class="card mb-3"><div class="card-body">
    <form method="GET" class="d-flex gap-2">
        <input type="text" name="search" class="form-control" placeholder="Search by name or phone..." value="{{ request('search') }}" style="max-width:300px;">
        <button type="submit" class="btn btn-secondary">Search</button>
        @if(request('search'))<a href="{{ route('suppliers.index') }}" class="btn btn-outline">Clear</a>@endif
    </form>
</div></div>
<div class="card"><div class="card-body p-0">
    <table class="table">
        <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Outstanding Balance</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($suppliers as $i => $s)
            <tr>
                <td>{{ $suppliers->firstItem() + $i }}</td>
                <td><strong>{{ $s->name }}</strong></td>
                <td>{{ $s->phone ?? '-' }}</td>
                <td>{{ $s->email ?? '-' }}</td>
                <td class="{{ $s->current_balance > 0 ? 'text-danger' : 'text-success' }}"><strong>₹{{ number_format($s->current_balance, 2) }}</strong></td>
                <td><span class="badge {{ $s->is_active ? 'badge-success' : 'badge-danger' }}">{{ $s->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td class="d-flex gap-1">
                    <a href="{{ route('suppliers.ledger', $s) }}" class="btn btn-sm btn-primary" title="View Ledger"><i data-lucide="book-open"></i></a>
                    <a href="{{ route('suppliers.edit', $s) }}" class="btn btn-sm btn-outline"><i data-lucide="edit-2"></i></a>
                    <form method="POST" action="{{ route('suppliers.destroy', $s) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger"><i data-lucide="trash-2"></i></button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted p-4">No suppliers found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-2">{{ $suppliers->links() }}</div>
@endsection
