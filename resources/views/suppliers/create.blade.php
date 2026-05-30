@extends('layouts.app')
@section('title', 'Add Supplier')
@section('content')
<div class="mb-3"><a href="{{ route('suppliers.index') }}" class="btn btn-outline"><i data-lucide="arrow-left"></i> Back</a></div>
<div class="card" style="max-width:700px;">
    <div class="card-header"><h3>Add New Supplier</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('suppliers.store') }}">@csrf
            <div class="grid-2 gap-3">
                <div class="form-group"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" value="{{ old('name') }}" required>@error('name')<span class="form-error">{{ $message }}</span>@enderror</div>
                <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="{{ old('phone') }}"></div>
                <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email') }}"></div>
                <div class="form-group"><label class="form-label">Status</label><select name="is_active" class="form-control"><option value="1" selected>Active</option><option value="0">Inactive</option></select></div>
            </div>
            <div class="form-group mt-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="3">{{ old('address') }}</textarea></div>
            <div class="d-flex gap-2 mt-3"><button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save</button><a href="{{ route('suppliers.index') }}" class="btn btn-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
