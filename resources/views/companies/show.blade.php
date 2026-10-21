@extends('layouts.app')
@section('title', $company->legal_name)
@section('content')
<div class="mb-4"><a href="{{ route('companies.index') }}" class="text-decoration-none">← All clients</a><div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mt-3"><div><div class="eyebrow">Client workspace</div><h1 class="page-title mb-1">{{ $company->legal_name }}</h1><p class="text-muted mb-0">TIN {{ $company->tin ?: 'Not provided' }} · {{ $company->registered_address ?: 'Address not provided' }}</p></div><div class="d-flex align-items-center gap-2"><span class="badge text-bg-success-subtle text-success fs-6">{{ ucfirst($company->status) }}</span>@can('update', $company)<a class="btn btn-outline-secondary" href="{{ route('companies.edit',$company) }}">Edit client</a>@endcan</div></div></div>
<div class="row g-3 mb-4">
@foreach([['Transactions',route('companies.transactions',$company),'Enter and review sales or purchases'],['Customers & suppliers',route('companies.counterparties',$company),'Maintain client counterparties'],['CSV / Excel imports',route('companies.imports',$company),'Validate and import transaction files'],['VAT reports',route('companies.reports',$company),'Prepare schedules and approvals']] as [$title,$url,$text])
<div class="col-md-6 col-xl-3"><a class="card h-100 text-decoration-none text-reset" href="{{ $url }}"><div class="card-body"><div class="eyebrow">{{ $title }}</div><div class="fw-semibold mt-2">{{ $text }}</div><div class="text-success mt-3">Open section →</div></div></a></div>
@endforeach
@if(in_array($company->userRole(auth()->user())?->value,['accountant','reviewer'],true))<div class="col-md-6 col-xl-3"><a class="card h-100 text-decoration-none text-reset" href="{{ route('companies.audit',$company) }}"><div class="card-body"><div class="eyebrow">Audit trail</div><div class="fw-semibold mt-2">Review changes and approvals</div><div class="text-success mt-3">Open section →</div></div></a></div>@endif
</div>
<div class="row g-4"><div class="col-lg-5"><div class="card"><div class="card-header">Client summary</div><div class="card-body"><div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Transaction records</span><strong>{{ $company->transactions_count }}</strong></div></div></div></div></div>
@endsection
