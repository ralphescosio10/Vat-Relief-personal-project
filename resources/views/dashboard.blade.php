@extends('layouts.app')
@section('title', 'Overview')
@section('content')
@push('head')
<style>
    .dashboard-stat-link{color:inherit;transition:transform .15s ease,box-shadow .15s ease}
    .dashboard-stat-link:hover{color:inherit;transform:translateY(-2px);box-shadow:0 7px 20px #132e2018}
    .dashboard-stat-link:focus-visible{outline:3px solid #1c6b55;outline-offset:3px}
</style>
@endpush
<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
    <div><div class="eyebrow mb-1">Firm workspace</div><h1 class="page-title mb-1">Good day, {{ auth()->user()->name }}</h1><p class="text-muted mb-0">Your client portfolio and reporting work at a glance.</p></div>
    <a class="btn btn-primary" href="{{ route('companies.create') }}">＋ Add client</a>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-4"><a class="card h-100 d-block text-decoration-none dashboard-stat-link" href="{{ route('companies.index') }}" aria-label="View client companies"><div class="card-body"><div class="eyebrow">Client companies</div><div class="stat">{{ $companies->count() }}</div><div class="text-muted">Active in your workspace</div></div></a></div>
    <div class="col-md-4"><a class="card h-100 d-block text-decoration-none dashboard-stat-link" href="{{ $companies->count() === 1 ? route('companies.transactions', $companies->first()) : route('companies.index') }}" aria-label="View transactions"><div class="card-body"><div class="eyebrow">Transactions</div><div class="stat">{{ number_format($transactionCount) }}</div><div class="text-muted">Across your assigned companies</div></div></a></div>
    <div class="col-md-4"><a class="card h-100 d-block text-decoration-none dashboard-stat-link" href="{{ $pendingReviewReport ? route('companies.reports.show', [$pendingReviewReport->company, $pendingReviewReport]) : '#recent-reports' }}" aria-label="View reports for review"><div class="card-body"><div class="eyebrow">Reports for review</div><div class="stat">{{ $pendingReports }}</div><div class="text-muted">Waiting for a reviewer</div></div></a></div>
</div>
<div class="row g-4">
    <div class="col-lg-7"><div class="card h-100"><div class="card-header d-flex justify-content-between"><span>Your client companies</span><a href="{{ route('companies.index') }}">All clients →</a></div>
        <div class="list-group list-group-flush">@forelse($companies as $company)<a class="list-group-item list-group-item-action px-4 py-3" href="{{ route('companies.show', $company) }}"><div class="d-flex justify-content-between align-items-center gap-2"><div><div class="fw-semibold">{{ $company->legal_name }}</div><small class="text-muted">TIN {{ $company->tin ?: 'Not provided' }}</small></div><span class="badge text-bg-light">{{ $company->transactions_count }} records</span></div></a>@empty<div class="p-4 text-muted">No client companies assigned yet.</div>@endforelse</div>
    </div></div>
    <div class="col-lg-5" id="recent-reports"><div class="card h-100"><div class="card-header">Recent VAT reports</div><div class="list-group list-group-flush">@forelse($recentReports as $report)<a class="list-group-item list-group-item-action px-4 py-3" href="{{ route('companies.reports.show', [$report->company, $report]) }}"><div class="fw-semibold">{{ $report->company->legal_name }}</div><small class="text-muted">{{ $report->period_start->format('M j, Y') }} – {{ $report->period_end->format('M j, Y') }}</small><span class="badge text-bg-light float-end">{{ str_replace('_', ' ', ucfirst($report->status)) }}</span></a>@empty<div class="p-4 text-muted">Reports you can access will appear here.</div>@endforelse</div></div></div>
</div>
<div class="card mt-4"><div class="card-header">Deadlines & client submissions</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th class="ps-4">Client / period</th><th>Deadline</th><th>Sales file</th><th>Purchase file</th><th>Supporting documents</th><th></th></tr></thead><tbody>
@forelse($submissionWatch as $report)<tr><td class="ps-4"><div class="fw-semibold">{{ $report->company->legal_name }}</div><small class="text-muted">{{ $report->period_start->format('M j') }} – {{ $report->period_end->format('M j, Y') }}</small></td><td>@if($report->due_date)<span class="{{ $report->due_date->isBefore(today()) ? 'text-danger fw-semibold' : '' }}">{{ $report->due_date->format('M j, Y') }}</span>@else<span class="text-muted">Not set</span>@endif</td><td>{{ $report->sales_received_at ? 'Received' : 'Missing' }}</td><td>{{ $report->purchases_received_at ? 'Received' : 'Missing' }}</td><td>{{ $report->supporting_docs_received_at ? 'Received' : 'Missing' }}</td><td><a href="{{ route('companies.reports.show',[$report->company,$report]) }}">Open</a></td></tr>@empty<tr><td colspan="6" class="text-center text-muted p-4">No reporting periods need attention.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
