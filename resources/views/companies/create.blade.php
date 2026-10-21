@extends('layouts.app')
@section('title', 'Add client')
@section('content')
<div class="mb-4"><a href="{{ route('companies.index') }}" class="text-decoration-none">← Client companies</a><div class="eyebrow mt-3">New client</div><h1 class="page-title">Register a company</h1></div>
<div class="card"><div class="card-body p-4"><form method="post" action="{{ route('companies.store') }}">@csrf
    <div class="row g-3"><div class="col-md-8"><label class="form-label">Registered legal name *</label><input class="form-control" name="legal_name" value="{{ old('legal_name') }}" required maxlength="255"></div><div class="col-md-4"><label class="form-label">Trade name</label><input class="form-control" name="trade_name" value="{{ old('trade_name') }}"></div>
    <div class="col-md-4"><label class="form-label">TIN</label><input class="form-control" name="tin" value="{{ old('tin') }}" maxlength="20" placeholder="Keep leading zeroes"></div><div class="col-md-4"><label class="form-label">Branch code</label><input class="form-control" name="branch_code" value="{{ old('branch_code') }}" maxlength="10"></div><div class="col-md-4"><label class="form-label">Default VAT rate (%)</label><input class="form-control" type="number" name="default_vat_rate" value="{{ old('default_vat_rate') }}" min="0" max="100" step="0.01" placeholder="Set per client"></div>
    <div class="col-12"><label class="form-label">Registered address</label><textarea class="form-control" name="registered_address" rows="3">{{ old('registered_address') }}</textarea></div></div><p class="small text-muted mt-3">Default rate is a convenience only. Confirm the correct VAT treatment and rate for this company and transaction type.</p>
    <div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light" href="{{ route('companies.index') }}">Cancel</a><button class="btn btn-primary">Create client</button></div>
</form></div></div>
@endsection
