@extends('layouts.app')
@section('title', 'Edit transaction')
@section('content')
<div class="mb-4"><a href="{{ route('companies.transactions',$company) }}" class="text-decoration-none">← Transactions</a><div class="eyebrow mt-3">Update record</div><h1 class="page-title">Edit {{ $transaction->invoice_number }}</h1></div>
<div class="card"><div class="card-body"><form method="post" action="{{ route('companies.transactions.update',[$company,$transaction]) }}">@csrf @method('put')
<div class="row g-3">
    <div class="col-md-3"><label class="form-label">Type</label><select class="form-select" name="type"><option value="sale" @selected($transaction->type==='sale')>Sale</option><option value="purchase" @selected($transaction->type==='purchase')>Purchase</option></select></div>
    <div class="col-md-3"><label class="form-label">Invoice number</label><input class="form-control" name="invoice_number" value="{{ $transaction->invoice_number }}" required></div>
    <div class="col-md-3"><label class="form-label">Invoice date</label><input class="form-control" type="date" name="invoice_date" value="{{ $transaction->invoice_date->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
    <div class="col-md-3"><label class="form-label">Customer / supplier</label><select class="form-select" name="counterparty_id"><option value="">Not specified</option>@foreach($counterparties as $party)<option value="{{ $party->id }}" @selected($party->id===$transaction->counterparty_id)>{{ $party->name }} · {{ ucfirst($party->type) }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Classification</label><select class="form-select" name="classification">@foreach(['vatable'=>'VATable','exempt'=>'VAT-exempt','zero_rated'=>'Zero-rated'] as $key=>$label)<option value="{{ $key }}" @selected($transaction->classification===$key)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Invoice amount</label><input class="form-control" type="number" step="0.01" min="0" name="amount" value="{{ number_format($transaction->vat_inclusive ? $transaction->total_amount : $transaction->taxable_amount,2,'.','') }}" required></div>
    <div class="col-md-2"><label class="form-label">VAT rate (%)</label><input class="form-control" type="number" step="0.01" min="0" max="100" name="vat_rate" value="{{ $transaction->vat_rate }}" required></div>
    <div class="col-md-4 d-flex align-items-end gap-3"><div class="form-check mb-2"><input class="form-check-input" id="vat_inclusive" name="vat_inclusive" type="checkbox" value="1" @checked($transaction->vat_inclusive)><label class="form-check-label" for="vat_inclusive">Amount includes VAT</label></div><button class="btn btn-primary ms-auto">Save changes</button></div>
</div></form></div></div>
@endsection
