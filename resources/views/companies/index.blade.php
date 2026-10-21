@extends('layouts.app')
@section('title', 'Client companies')
@section('content')
<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3"><div><div class="eyebrow">Portfolio</div><h1 class="page-title mb-1">Client companies</h1><p class="text-muted mb-0">Each company has its own separate accounting records.</p></div><a class="btn btn-primary" href="{{ route('companies.create') }}">＋ Add client</a></div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th class="ps-4">Company</th><th>TIN</th><th>Status</th><th>Transactions</th><th></th></tr></thead><tbody>
@forelse($companies as $company)<tr><td class="ps-4"><a class="fw-semibold text-decoration-none" href="{{ route('companies.show', $company) }}">{{ $company->legal_name }}</a>@if($company->trade_name)<div class="small text-muted">{{ $company->trade_name }}</div>@endif</td><td>{{ $company->tin ?: '—' }}</td><td><span class="badge text-bg-success-subtle text-success">{{ ucfirst($company->status) }}</span></td><td>{{ $company->transactions_count }}</td><td><a class="btn btn-sm btn-outline-secondary" href="{{ route('companies.show', $company) }}">Open</a></td></tr>@empty<tr><td colspan="5" class="text-center text-muted p-5">No companies found.</td></tr>@endforelse
</tbody></table></div><div class="p-3">{{ $companies->links() }}</div></div>
@endsection
