@extends('layouts.app')
@section('title', 'Products')
@section('content')
<div class="d-flex justify-between align-items-center mb-3">
    <h2>Product Master</h2>
    <a href="{{ route('products.create') }}" class="btn btn-primary"><i data-lucide="plus"></i> Add Product</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control" placeholder="Search products..." value="{{ request('search') }}" style="max-width:300px;">
            <select name="category" class="form-control" style="max-width:200px;" onchange="this.form.submit()">
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary">Search</button>
            @if(request('search') || request('category'))
                <a href="{{ route('products.index') }}" class="btn btn-outline">Clear</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr><th>#</th><th>Name</th><th>SKU</th><th>Unit</th><th>Category</th><th>MRP</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            @forelse($products as $i => $product)
                <tr>
                    <td>{{ $products->firstItem() + $i }}</td>
                    <td><strong>{{ $product->name }}</strong></td>
                    <td>{{ $product->sku ?? '-' }}</td>
                    <td>{{ $product->unit }}</td>
                    <td>{{ $product->category ?? '-' }}</td>
                    <td>₹{{ number_format($product->mrp, 2) }}</td>
                    <td><span class="badge {{ $product->is_active ? 'badge-success' : 'badge-danger' }}">{{ $product->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="d-flex gap-1">
                        <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-outline"><i data-lucide="edit-2"></i></a>
                        <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Delete this product?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger"><i data-lucide="trash-2"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted p-4">No products found. <a href="{{ route('products.create') }}">Add one now</a></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-2">{{ $products->links() }}</div>
@endsection
