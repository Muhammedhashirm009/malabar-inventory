@extends('layouts.app')
@section('title', 'Customers')
@section('content')
<div class="d-flex justify-between align-items-center mb-3">
    <h2>Customer Master</h2>
    <a href="{{ route('customers.create') }}" class="btn btn-primary"><i data-lucide="plus"></i> Add Customer</a>
</div>
<div class="card mb-3"><div class="card-body">
    <form method="GET" class="d-flex gap-2">
        <input type="text" name="search" class="form-control" placeholder="Search by name or phone..." value="{{ request('search') }}" style="max-width:300px;">
        <button type="submit" class="btn btn-secondary">Search</button>
        @if(request('search'))<a href="{{ route('customers.index') }}" class="btn btn-outline">Clear</a>@endif
    </form>
</div></div>
<div class="card"><div class="card-body p-0">
    <table class="table">
        <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Current Balance</th><th>Credit Limit</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($customers as $i => $c)
            <tr>
                <td>{{ $customers->firstItem() + $i }}</td>
                <td><strong>{{ $c->name }}</strong></td>
                <td>{{ $c->phone ?? '-' }}</td>
                <td class="{{ $c->current_balance > 0 ? 'text-danger' : 'text-success' }}"><strong>₹{{ number_format($c->current_balance, 2) }}</strong></td>
                <td>₹{{ number_format($c->credit_limit, 2) }}</td>
                <td class="d-flex gap-1">
                    <a href="{{ route('customers.ledger', $c) }}" class="btn btn-sm btn-primary" title="View Ledger"><i data-lucide="book-open"></i></a>
                    <a href="{{ route('customers.edit', $c) }}" class="btn btn-sm btn-outline"><i data-lucide="edit-2"></i></a>
                    <form method="POST" action="{{ route('customers.destroy', $c) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger"><i data-lucide="trash-2"></i></button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted p-4">No customers found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-2">{{ $customers->links() }}</div>
@endsection
