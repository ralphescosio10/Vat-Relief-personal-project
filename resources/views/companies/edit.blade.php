@extends('layouts.app')
@section('title', 'Edit client')
@section('content')
<div class="mb-4"><a href="{{ route('companies.show',$company) }}" class="text-decoration-none">← {{ $company->legal_name }}</a><div class="eyebrow mt-3">Client configuration</div><h1 class="page-title">Edit company</h1></div>
<div class="card"><div class="card-body p-4"><form method="post" action="{{ route('companies.update',$company) }}">@csrf @method('put')
    <div class="row g-3"><div class="col-md-8"><label class="form-label">Registered legal name *</label><input class="form-control" name="legal_name" value="{{ old('legal_name',$company->legal_name) }}" required></div><div class="col-md-4"><label class="form-label">Trade name</label><input class="form-control" name="trade_name" value="{{ old('trade_name',$company->trade_name) }}"></div>
    <div class="col-md-3"><label class="form-label">TIN</label><input class="form-control" name="tin" value="{{ old('tin',$company->tin) }}"></div><div class="col-md-3"><label class="form-label">Branch code</label><input class="form-control" name="branch_code" value="{{ old('branch_code',$company->branch_code) }}"></div><div class="col-md-3"><label class="form-label">Default VAT rate (%)</label><input class="form-control" type="number" name="default_vat_rate" value="{{ old('default_vat_rate',$company->default_vat_rate) }}" min="0" max="100" step="0.01"></div><div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" @selected($company->status==='active')>Active</option><option value="inactive" @selected($company->status==='inactive')>Inactive</option></select></div>
    <div class="col-12"><label class="form-label">Registered address</label><textarea class="form-control" name="registered_address" rows="3">{{ old('registered_address',$company->registered_address) }}</textarea></div></div>
    <div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light" href="{{ route('companies.show',$company) }}">Cancel</a><button class="btn btn-primary">Save changes</button></div>
</form></div></div>
@endsection
