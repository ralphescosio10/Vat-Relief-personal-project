@extends('layouts.app')
@section('title', 'VAT reports')
@section('content')
<div class="mb-4"><a href="{{ route('companies.show',$company) }}" class="text-decoration-none">← {{ $company->legal_name }}</a><div class="eyebrow mt-3">Reporting workflow</div><h1 class="page-title">VAT relief schedules</h1><p class="text-muted">Generate a transaction snapshot by period, then submit it for review and approval.</p></div>
@if($canPrepareReports)<div class="card mb-4"><div class="card-header">Prepare a report</div><div class="card-body"><form method="post" action="{{ route('companies.reports.generate',$company) }}" class="row g-3 align-items-end">@csrf
    <div class="col-md-4"><label class="form-label">Period start</label><input class="form-control" type="date" name="period_start" value="{{ old('period_start', now()->startOfMonth()->toDateString()) }}" required></div>
    <div class="col-md-4"><label class="form-label">Period end</label><input class="form-control" type="date" name="period_end" value="{{ old('period_end', now()->endOfMonth()->toDateString()) }}" required></div>
    <div class="col-md-4"><button class="btn btn-primary">Generate schedule</button></div>
</form></div></div>@endif
<div class="card"><div class="card-header">Report history</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th class="ps-4">Period</th><th>Status</th><th>Prepared</th><th>Updated</th><th></th></tr></thead><tbody>@forelse($reports as $report)<tr><td class="ps-4 fw-semibold">{{ $report->period_start->format('M j, Y') }} – {{ $report->period_end->format('M j, Y') }}</td><td><span class="badge text-bg-light">{{ str_replace('_',' ',ucfirst($report->status)) }}</span></td><td>{{ $report->prepared_by ? \App\Models\User::find($report->prepared_by)?->name : '—' }}</td><td>{{ $report->updated_at->format('M j, Y') }}</td><td><a class="btn btn-sm btn-outline-secondary" href="{{ route('companies.reports.show',[$company,$report]) }}">Open</a></td></tr>@empty<tr><td colspan="5" class="text-center text-muted p-5">No reports prepared for this company.</td></tr>@endforelse</tbody></table></div><div class="p-3">{{ $reports->links() }}</div></div>
@endsection
