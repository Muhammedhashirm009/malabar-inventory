@extends('layouts.app')
@section('title', 'Payments')
@section('content')
<div class="d-flex justify-between align-items-center mb-3">
    <h2>Payments Received</h2>
    <a href="{{ route('payments.create') }}" class="btn btn-primary"><i data-lucide="plus"></i> Receive Payment</a>
</div>
<div class="card"><div class="card-body p-0">
    <table class="table">
        <thead><tr><th>Date</th><th>Customer</th><th>Amount</th><th>Description</th></tr></thead>
        <tbody>
        @forelse($payments as $p)
            <tr>
                <td>{{ $p->transaction_date->format('d M Y') }}</td>
                <td>{{ $p->customer->name ?? '-' }}</td>
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
