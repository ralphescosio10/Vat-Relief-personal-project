@extends('layouts.app')
@section('title', 'Transaction imports')
@section('content')
<div class="mb-4"><a href="{{ route('companies.show',$company) }}" class="text-decoration-none">← {{ $company->legal_name }}</a><div class="eyebrow mt-3">File processing</div><h1 class="page-title">CSV imports</h1><p class="text-muted">Upload a CSV, review row-level errors and duplicates, then import valid rows only.</p></div>
<div class="card mb-4"><div class="card-header">Upload transaction file</div><div class="card-body">
    <p class="small text-muted">Required headers: <code>type, invoice_number, invoice_date, classification, amount, vat_rate</code>. Optional: <code>counterparty_name, counterparty_tin, vat_inclusive</code>. Use <code>sale</code>/<code>purchase</code> and <code>vatable</code>/<code>exempt</code>/<code>zero_rated</code>.</p>
    @if($canCommit || $company->userRole(auth()->user())?->value==='client_user')<form method="post" enctype="multipart/form-data" action="{{ route('companies.imports.store',$company) }}" class="d-flex gap-3 align-items-end flex-wrap">@csrf<div><label class="form-label" for="file">CSV or Excel workbook (.xlsx), max 10 MB</label><input class="form-control" type="file" id="file" name="file" accept=".csv,.txt,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></div><button class="btn btn-primary">Validate file</button></form>@endif
</div></div>
@if($preview)
    @php($previewRows = json_decode($preview->results, true) ?? [])
    <div class="card mb-4"><div class="card-header d-flex justify-content-between align-items-center"><span>Preview · {{ $preview->filename }}</span><span class="badge text-bg-warning">{{ $preview->invalid_count }} unresolved rows</span></div>
    @if($canCommit)<form method="post" action="{{ route('companies.imports.commit',[$company,$preview->id]) }}">@csrf @endif
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th class="ps-4">Row</th><th>Invoice & date</th><th>Type / class</th><th>Counterparty</th><th>Amount / rate</th><th>Status & validation</th></tr></thead><tbody>
    @foreach($previewRows as $index => $row)
        @if(($row['imported'] ?? false))<tr><td class="ps-4">{{ $row['line'] }}</td><td>{{ $row['data']['invoice_number'] }}<br><small>{{ $row['data']['invoice_date'] }}</small></td><td>{{ ucfirst($row['data']['type']) }} · {{ str_replace('_',' ',$row['data']['classification']) }}</td><td>{{ $row['data']['counterparty_name'] ?: '—' }}</td><td>{{ $row['data']['amount'] }} / {{ $row['data']['vat_rate'] }}%</td><td><span class="badge text-bg-success">Imported</span></td></tr>
        @else
        <tr><td class="ps-4">{{ $row['line'] }}</td>
            @if($canCommit && !$row['valid'])
                <td><input class="form-control form-control-sm mb-1" name="corrections[{{ $index }}][invoice_number]" value="{{ $row['data']['invoice_number'] }}" placeholder="Invoice no."><input class="form-control form-control-sm" type="date" name="corrections[{{ $index }}][invoice_date]" value="{{ $row['data']['invoice_date'] }}"></td>
                <td><select class="form-select form-select-sm mb-1" name="corrections[{{ $index }}][type]"><option value="sale" @selected($row['data']['type']==='sale')>Sale</option><option value="purchase" @selected($row['data']['type']==='purchase')>Purchase</option></select><select class="form-select form-select-sm" name="corrections[{{ $index }}][classification]">@foreach(['vatable'=>'VATable','exempt'=>'Exempt','zero_rated'=>'Zero-rated'] as $key=>$label)<option value="{{ $key }}" @selected($row['data']['classification']===$key)>{{ $label }}</option>@endforeach</select></td>
                <td><input class="form-control form-control-sm mb-1" name="corrections[{{ $index }}][counterparty_name]" value="{{ $row['data']['counterparty_name'] }}" placeholder="Customer/supplier"><input class="form-control form-control-sm" name="corrections[{{ $index }}][counterparty_tin]" value="{{ $row['data']['counterparty_tin'] }}" placeholder="TIN"></td>
                <td><input class="form-control form-control-sm mb-1" type="number" step="0.01" min="0" name="corrections[{{ $index }}][amount]" value="{{ $row['data']['amount'] }}" placeholder="Amount"><input class="form-control form-control-sm" type="number" step="0.01" min="0" max="100" name="corrections[{{ $index }}][vat_rate]" value="{{ $row['data']['vat_rate'] }}" placeholder="VAT rate %"><input type="hidden" name="corrections[{{ $index }}][vat_inclusive]" value="{{ $row['data']['vat_inclusive'] ? '1' : '0' }}"></td>
            @else
                <td>{{ $row['data']['invoice_number'] }}<br><small>{{ $row['data']['invoice_date'] }}</small></td><td>{{ ucfirst($row['data']['type']) }} · {{ str_replace('_',' ',$row['data']['classification']) }}</td><td>{{ $row['data']['counterparty_name'] ?: '—' }}</td><td>{{ $row['data']['amount'] }} / {{ $row['data']['vat_rate'] }}%</td>
            @endif
            <td>@if($row['valid'])<span class="badge text-bg-success">Ready</span>@else<span class="badge text-bg-danger">Needs correction</span>@endif @foreach($row['errors'] as $error)<div class="small text-danger mt-1">{{ $error }}</div>@endforeach</td>
        </tr>
        @endif
    @endforeach
    </tbody></table></div>
    <div class="card-body border-top d-flex justify-content-between align-items-center"><span class="small text-muted">Correct flagged rows and resubmit; valid rows import once and stay marked imported.</span>@if($canCommit)<button class="btn btn-primary" @disabled(count(array_filter($previewRows,fn($row)=>$row['valid'] && !($row['imported']??false)))===0 && $preview->invalid_count===0)>Import ready rows / save corrections</button>@else<span class="badge text-bg-info">Submitted for accountant review</span>@endif</div>
    @if($canCommit)</form>@endif
    </div>
@endif
<div class="card"><div class="card-header">Import history</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th class="ps-4">File</th><th>Uploaded</th><th>Rows</th><th>Invalid</th><th>Imported</th><th>Status</th></tr></thead><tbody>
@forelse($batches as $batch)<tr><td class="ps-4">@if($batch->status==='validated')<a href="{{ route('companies.imports',[$company,'batch'=>$batch->id]) }}">{{ $batch->filename }}</a>@else{{ $batch->filename }}@endif</td><td>{{ \Illuminate\Support\Carbon::parse($batch->created_at)->format('M j, Y H:i') }}</td><td>{{ $batch->row_count }}</td><td>{{ $batch->invalid_count }}</td><td>{{ $batch->imported_count }}</td><td><span class="badge text-bg-light">{{ ucfirst($batch->status) }}</span></td></tr>@empty<tr><td colspan="6" class="text-center text-muted p-5">No imports yet.</td></tr>@endforelse</tbody></table></div><div class="p-3">{{ $batches->links() }}</div></div>
@endsection
