@extends('layouts.app')
@section('title', 'Supplier Payments')
@section('content')
<div class="d-flex justify-between align-items-center mb-3">
    <h2>Supplier Payments Made</h2>
    <a href="{{ route('supplier-payments.create') }}" class="btn btn-primary"><i data-lucide="plus"></i> Make Payment</a>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table">
        <thead><tr><th>Date</th><th>Supplier</th><th>Amount Paid</th><th>Description</th></tr></thead>
        <tbody>
        @forelse($payments as $p)
            <tr>
                <td>{{ $p->transaction_date->format('d M Y') }}</td>
                <td>{{ $p->supplier->name ?? '-' }}</td>
                <td class="text-success"><strong>₹{{ number_format($p->amount, 2) }}</strong></td>
                <td>{{ $p->description }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted p-4">No payments recorded.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-2">{{ $payments->links() }}</div>
@endsection
