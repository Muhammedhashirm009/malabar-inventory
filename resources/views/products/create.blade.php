@extends('layouts.app')
@section('title', 'Add Product')
@section('content')
<div class="mb-3"><a href="{{ route('products.index') }}" class="btn btn-outline"><i data-lucide="arrow-left"></i> Back to Products</a></div>
<div class="card" style="max-width:800px;">
    <div class="card-header"><h3>Add New Product</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('products.store') }}">
            @csrf
            <div class="grid-2 gap-3">
                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    @error('name') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">SKU</label>
                    <input type="text" name="sku" class="form-control" value="{{ old('sku') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Unit *</label>
                    <select name="unit" class="form-control" required>
                        @foreach(['pcs', 'kg', 'ltr', 'box', 'meter', 'dozen', 'set', 'pair'] as $u)
                            <option value="{{ $u }}" {{ old('unit', 'pcs') == $u ? 'selected' : '' }}>{{ ucfirst($u) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-control" value="{{ old('category') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">MRP (₹) *</label>
                    <input type="number" name="mrp" class="form-control" step="0.01" min="0" value="{{ old('mrp', '0') }}" required>
                    @error('mrp') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-control">
                        <option value="1" selected>Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="form-group mt-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Product</button>
                <a href="{{ route('products.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
