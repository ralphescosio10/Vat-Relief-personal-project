@extends('layouts.app')
@section('title', 'Customers and suppliers')
@section('content')
<div class="mb-4"><a href="{{ route('companies.show',$company) }}" class="text-decoration-none">← {{ $company->legal_name }}</a><div class="eyebrow mt-3">Company records</div><h1 class="page-title">Customers & suppliers</h1></div>
<div class="row g-4">@if($canManageRecords)<div class="col-lg-4"><div class="card"><div class="card-header">Add a record</div><div class="card-body"><form method="post" action="{{ route('companies.counterparties.store',$company) }}">@csrf
    <div class="mb-3"><label class="form-label">Type *</label><select name="type" class="form-select" required><option value="customer">Customer</option><option value="supplier">Supplier</option></select></div>
    <div class="mb-3"><label class="form-label">Legal name *</label><input name="name" class="form-control" required maxlength="255"></div>
    <div class="mb-3"><label class="form-label">TIN</label><input name="tin" class="form-control" maxlength="20"></div>
    <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="3"></textarea></div><button class="btn btn-primary w-100">Add record</button>
</form></div></div></div>@endif
<div class="{{ $canManageRecords ? 'col-lg-8' : 'col-12' }}"><div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th class="ps-4">Name</th><th>Type</th><th>TIN</th><th></th></tr></thead><tbody>
@forelse($counterparties as $item)<tr><td class="ps-4 fw-semibold">{{ $item->name }}</td><td>{{ ucfirst($item->type) }}</td><td>{{ $item->tin ?: '—' }}</td><td>@if($canManageRecords)<form method="post" action="{{ route('companies.counterparties.destroy',[$company,$item]) }}" onsubmit="return confirm('Delete this customer or supplier?')">@csrf @method('delete')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endif</td></tr>@empty<tr><td colspan="4" class="text-center text-muted p-5">No customers or suppliers yet.</td></tr>@endforelse
</tbody></table></div><div class="p-3">{{ $counterparties->links() }}</div></div></div></div>
@endsection
